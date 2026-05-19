<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Picqer\Barcode\Types\TypeCode128;
use Picqer\Barcode\Renderers\PngRenderer;
use Illuminate\Support\Facades\Hash;
use Mpdf\Mpdf;

/**
 * Per-piece tracking stickers.
 *
 * One row in store_sea / store_sky represents a batch (e.g. 5 cartons for
 * one client). Each physical carton needs its own sticker so the warehouse
 * can scan an individual piece. This controller:
 *
 *  1. mints one shipment_pieces row per physical piece (idempotent)
 *  2. renders a 100x150mm thermal-label PDF with QR + key fields
 *  3. exposes a public /track/{code} page that returns ONLY safe fields
 *     (no money, no supplier names, no balances)
 */
class shipmentStickersController extends Controller
{
    /** Tables we know how to attach pieces to. */
    private const ALLOWED_SOURCES = ['store_sea', 'store_sky'];

    /* ============================================================
     *  Public API: piece generation
     * ============================================================ */

    /**
     * Ensure $count pieces exist for ($sourceTable, $sourceId). Idempotent:
     * if some already exist (e.g. user re-saved the row), only the missing
     * indices get created. If $count shrinks below the current piece_total,
     * the trailing pieces are flipped to 'cancelled' so previously-printed
     * stickers stop resolving as active.
     *
     * Returns the full set of active piece rows for the source.
     */
    public function ensurePieces(string $sourceTable, int $sourceId, int $count, ?int $clientId = null): array
    {
        if (!in_array($sourceTable, self::ALLOWED_SOURCES, true)) {
            throw new \InvalidArgumentException("source_table $sourceTable not allowed");
        }
        if ($count < 1) $count = 1;

        $userId = auth()->user()?->id;

        DB::transaction(function () use ($sourceTable, $sourceId, $count, $clientId, $userId) {
            $existing = DB::table('shipment_pieces')
                ->where('source_table', $sourceTable)
                ->where('source_id', $sourceId)
                ->orderBy('piece_index')
                ->get();

            $existingByIndex = [];
            foreach ($existing as $p) $existingByIndex[$p->piece_index] = $p;

            // Insert any missing indices up to $count.
            for ($i = 1; $i <= $count; $i++) {
                if (isset($existingByIndex[$i])) {
                    // Reactivate if it was previously cancelled and the user
                    // bumped the count back up. Refresh piece_total snapshot.
                    if ($existingByIndex[$i]->status !== 'active'
                        || (int) $existingByIndex[$i]->piece_total !== $count) {
                        DB::table('shipment_pieces')
                            ->where('id', $existingByIndex[$i]->id)
                            ->update([
                                'status'      => 'active',
                                'piece_total' => $count,
                                'updated_at'  => date('Y-m-d H:i:s'),
                            ]);
                    }
                    continue;
                }
                // Retry on duplicate-key race: two concurrent receipts could
                // both pass mintTrackingCode()'s exists() check and race the
                // insert. The unique constraint will reject the loser; we
                // catch 1062 and try a fresh code rather than letting the
                // exception unwind the parent transaction (which would also
                // roll back the store_sea insert above us).
                $inserted = false;
                for ($try = 0; $try < 5 && !$inserted; $try++) {
                    try {
                        DB::table('shipment_pieces')->insert([
                            'tracking_code' => $this->mintTrackingCode(),
                            'source_table'  => $sourceTable,
                            'source_id'     => $sourceId,
                            'client_id'     => $clientId,
                            'piece_index'   => $i,
                            'piece_total'   => $count,
                            'status'        => 'active',
                            'created_by'    => $userId,
                            'created_at'    => date('Y-m-d H:i:s'),
                            'updated_at'    => date('Y-m-d H:i:s'),
                        ]);
                        $inserted = true;
                    } catch (\Illuminate\Database\QueryException $e) {
                        $sqlState = $e->errorInfo[0] ?? null;
                        $errno    = $e->errorInfo[1] ?? null;
                        // 23000/1062 = duplicate. The (source_table, source_id,
                        // piece_index) unique catches "another worker already
                        // minted index $i" — accept that as success and move on.
                        // The tracking_code unique we retry below.
                        if ($sqlState === '23000' && (int) $errno === 1062) {
                            if (str_contains((string) $e->getMessage(), 'src_idx_unique')) {
                                $inserted = true;
                                break;
                            }
                            continue;
                        }
                        throw $e;
                    }
                }
                if (!$inserted) {
                    throw new \RuntimeException(
                        "Could not insert shipment_piece for {$sourceTable}#{$sourceId} index {$i} after 5 attempts"
                    );
                }
            }

            // Cancel any pieces beyond $count (operator shrunk the batch).
            DB::table('shipment_pieces')
                ->where('source_table', $sourceTable)
                ->where('source_id', $sourceId)
                ->where('piece_index', '>', $count)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
        });

        return DB::table('shipment_pieces')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('status', 'active')
            ->orderBy('piece_index')
            ->get()
            ->all();
    }

    /**
     * Cancel every active piece for a given source row. Called when the
     * source store_sea / store_sky row is cancelled.
     */
    public function cancelPiecesFor(string $sourceTable, int $sourceId): void
    {
        if (!in_array($sourceTable, self::ALLOWED_SOURCES, true)) return;
        DB::table('shipment_pieces')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('status', 'active')
            ->update(['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /* ============================================================
     *  Sticker PDF — 100x150mm thermal label, one piece per page
     * ============================================================ */

    public function stickerPdf(Request $request, string $sourceTable, int $sourceId)
    {
        $this->requireAuth();
        if (!in_array($sourceTable, self::ALLOWED_SOURCES, true)) abort(404);

        $source = DB::table($sourceTable)->where('id', $sourceId)->first();
        if (!$source) abort(404);

        // Self-heal pieces for legacy rows before rendering.
        $this->ensurePiecesExist($sourceTable, $source);

        $pdf = $this->buildStickersPdf([['table' => $sourceTable, 'row' => $source]]);
        $filename = 'stickers-' . $sourceTable . '-' . $sourceId . '.pdf';
        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /* ============================================================
     *  Bulk sticker PDF for an entire container/trip.
     *
     *  POST /shipping/stickers/container/{containerTable}/{id}
     *  Body: print_pin=NNNN
     *
     *  Verifies the PIN configured in settings, walks every store_out_*
     *  row attached to the container, dereferences each one to its
     *  store_* source row, and emits ONE PDF with stickers for every
     *  piece across every shipment.
     * ============================================================ */
    public function bulkContainerStickers(Request $request, string $containerTable, int $containerId)
    {
        $this->requireAuth();

        // Map container_table -> matching store_out_* and store_* tables.
        $mapping = [
            'containers_sea' => ['out' => 'store_out_sea', 'in' => 'store_sea'],
            'containers_sky' => ['out' => 'store_out_sky', 'in' => 'store_sky'],
        ];
        if (!isset($mapping[$containerTable])) abort(404);

        $settings = (new settingsController())->get();
        $hash     = (string) ($settings['print_pin_hash'] ?? '');
        if ($hash === '') {
            return response()->json([
                'type'    => 'pin_not_configured',
                'message' => 'A print confirmation PIN has not been set yet. Configure it in Settings → General before bulk-printing.',
            ], 409);
        }
        $pin = trim((string) $request->input('print_pin', ''));
        if ($pin === '' || !Hash::check($pin, $hash)) {
            return response()->json([
                'type'    => 'wrong_pin',
                'message' => 'Incorrect PIN.',
            ], 403);
        }

        $container = DB::table($containerTable)->where('id', $containerId)->first();
        if (!$container) abort(404);

        // Every outbound entry attached to this container → unique source
        // (in_id) rows. We dedupe in_ids because the same source row can
        // produce multiple out rows (partial deliveries) but its pieces
        // were minted once at receipt.
        $inIds = DB::table($mapping[$containerTable]['out'])
            ->where('container_id', $containerId)
            ->pluck('in_id')
            ->map(fn($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($inIds)) {
            return response()->json([
                'type'    => 'empty_container',
                'message' => 'No shipments are attached to this container yet.',
            ], 422);
        }

        $sourceTable = $mapping[$containerTable]['in'];
        $rows = DB::table($sourceTable)->whereIn('id', $inIds)->orderBy('id')->get();

        $sources = [];
        foreach ($rows as $row) {
            $this->ensurePiecesExist($sourceTable, $row);
            $sources[] = ['table' => $sourceTable, 'row' => $row];
        }

        $pdf = $this->buildStickersPdf($sources);
        $filename = 'stickers-container-' . $containerTable . '-' . $containerId . '.pdf';
        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /* ============================================================
     *  Shared sticker rendering
     * ============================================================ */

    /**
     * Make sure shipment_pieces exists for the given source row. Used by
     * both the single-row sticker endpoint and the bulk endpoint so legacy
     * rows (or rows whose pieces never auto-generated) still produce output.
     */
    private function ensurePiecesExist(string $sourceTable, object $source): void
    {
        $exists = DB::table('shipment_pieces')
            ->where('source_table', $sourceTable)
            ->where('source_id', $source->id)
            ->where('status', 'active')
            ->exists();
        if ($exists) return;
        $count    = max(1, (int) ($source->number ?? 1));
        $clientId = (int) ($source->client_id ?? 0) ?: null;
        $this->ensurePieces($sourceTable, (int) $source->id, $count, $clientId);
    }

    /**
     * Render a single PDF combining stickers for every source row passed in.
     * Each row produces one page per active shipment_piece. mPDF concatenates
     * them by walking the @foreach in the blade template.
     */
    private function buildStickersPdf(array $sources): string
    {
        $settings = (new settingsController())->get();
        $prefix   = $this->resolvePrefix($settings);

        $barcoder   = new TypeCode128();
        $pngBarcode = new PngRenderer();
        $lang       = new langController();

        // Build the per-source view payload. The template loops over
        // top-level $batches; each batch has source + client + stickers
        // (the pieces). Keeping the schema flat makes it easy for the
        // template to render either mode without conditional logic.
        //
        // Pre-load all clients in one query (bulk endpoint can pass dozens
        // of sources — without this the loop below was 1+N).
        $clientIds = collect($sources)
            ->pluck('row.client_id')
            ->filter()
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
        $clientsById = empty($clientIds)
            ? collect()
            : DB::table('clients')->whereIn('id', $clientIds)->get()->keyBy('id');

        $batches = [];
        foreach ($sources as $s) {
            $sourceTable = $s['table'];
            $source      = $s['row'];

            $pieces = DB::table('shipment_pieces')
                ->where('source_table', $sourceTable)
                ->where('source_id', $source->id)
                ->where('status', 'active')
                ->orderBy('piece_index')
                ->get();

            $client = $source->client_id
                ? ($clientsById[$source->client_id] ?? null)
                : null;

            $stickers = [];
            foreach ($pieces as $p) {
                $url = url('/track/' . $p->tracking_code);
                $qr  = (new Builder())->build(
                    data: $url,
                    size: 380,
                    margin: 4,
                    errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                );
                $barcodePng = $pngBarcode->render($barcoder->getBarcode($p->tracking_code), 560, 90);
                $stickers[] = [
                    'piece'            => $p,
                    'qr_data_uri'      => $qr->getDataUri(),
                    'barcode_data_uri' => 'data:image/png;base64,' . base64_encode($barcodePng),
                    'public_url'       => $url,
                    'pretty_code'      => $this->prettyCode($p->tracking_code, $prefix),
                ];
            }

            $batches[] = [
                'source'   => $source,
                'client'   => $client,
                'mode'     => $sourceTable === 'store_sky' ? 'air' : 'sea',
                'stickers' => $stickers,
            ];
        }

        $html = view('pages.shipping.stickers.sticker_pdf', [
            'batches'  => $batches,
            'lang'     => $lang,
            'settings' => $settings,
        ])->render();

        // 100x150mm portrait. mPDF expects [width, height] in mm.
        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => [100, 150],
            'orientation'    => 'P',
            'default_font'   => 'dejavusans',
            'margin_top'     => 4,
            'margin_bottom'  => 4,
            'margin_left'    => 4,
            'margin_right'   => 4,
            'margin_header'  => 0,
            'margin_footer'  => 0,
        ]);
        $mpdf->WriteHTML($html);
        return $mpdf->Output('stickers.pdf', 'S');
    }

    /* ============================================================
     *  Public tracking page — NO AUTH
     *
     *  Anyone with the URL can read it (that's the whole point of a
     *  scannable QR), so we curate the field list carefully:
     *    - tracking code, piece N of M, status
     *    - shipment fields a recipient already knows (company, brand,
     *      ship_from, category, type, kg, cbm)
     *    - dates
     *  Strictly excluded:
     *    - prices, costs, commissions, exchange rates
     *    - supplier identities
     *    - client balance/internal notes
     * ============================================================ */
    public function publicTrack(string $code)
    {
        $settings = (new settingsController())->get();
        $prefix   = $this->resolvePrefix($settings);

        // Accept both the raw 12-char code (what the QR encodes) and the
        // human-friendly "PREFIX-XXXX-XXXX-XXXX" form (what's printed on
        // the sticker). Strip non-alphanum, then peel the configured prefix
        // (or any prefix that puts the input over 12 chars) since it's
        // display-only and never part of the stored code.
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code));
        if ($prefix !== '' && str_starts_with($code, $prefix) && strlen($code) === 12 + strlen($prefix)) {
            $code = substr($code, strlen($prefix));
        }
        $piece = DB::table('shipment_pieces')->where('tracking_code', $code)->first();
        if (!$piece) {
            return response(view('pages.shipping.stickers.track_not_found'), 404);
        }

        // Defence-in-depth: writes already gate source_table against ALLOWED_SOURCES,
        // but the value lives in the DB so we re-validate before using it as a table name.
        if (!in_array($piece->source_table, self::ALLOWED_SOURCES, true)) {
            return response(view('pages.shipping.stickers.track_not_found'), 404);
        }

        $source = DB::table($piece->source_table)->where('id', $piece->source_id)->first();
        $client = $piece->client_id
            ? DB::table('clients')->where('id', $piece->client_id)->first()
            : null;

        $status = $this->deriveStatus($piece, $source);

        // Carefully filtered view-model. Anything not listed here is not
        // exposed publicly — adding new fields requires explicit edit.
        $vm = [
            'tracking_code' => $this->prettyCode($piece->tracking_code, $prefix),
            'piece_index'   => (int) $piece->piece_index,
            'piece_total'   => (int) $piece->piece_total,
            'mode'          => $piece->source_table === 'store_sky' ? 'air' : 'sea',
            'status'        => $status['label'],
            'status_key'    => $status['key'],
            'last_update'   => $status['date'],
            'piece_active'  => $piece->status === 'active',

            'company'       => $source->company_name ?? null,
            'ship_from'     => $source->ship_from   ?? null,
            'category'      => $source->category    ?? null,
            'type'          => $source->type        ?? null,
            'brand'         => $source->brand       ?? null,
            // kg/cbm are operator-typed text columns — coerce to a number
            // or null so the public page never prints "N/A KG" or similar.
            'kg'            => is_numeric($source->kg  ?? null) ? (float) $source->kg  : null,
            'cbm'           => is_numeric($source->cbm ?? null) ? (float) $source->cbm : null,
            'received_date' => $source->created_date ?? null,

            // Public-facing client identifier: the operator-assigned code
            // (e.g. "C-0042") only — never the internal id and never the
            // client's full balance/contact info.
            'client_code'   => $client->code ?? null,
        ];

        return response(view('pages.shipping.stickers.track_public', [
            'p'        => $vm,
            'settings' => $settings,
        ]));
    }

    /* ============================================================
     *  Internals
     * ============================================================ */

    /**
     * Derive a human-readable shipment status for the public page by
     * walking the source chain. Order from "newest event wins":
     *   delivered → shipped/in container → received at warehouse → cancelled.
     */
    private function deriveStatus($piece, $source): array
    {
        if ($piece->status === 'cancelled') {
            return ['key' => 'cancelled', 'label' => 'Cancelled', 'date' => null];
        }
        if (!$source) {
            return ['key' => 'unknown', 'label' => 'Unknown', 'date' => null];
        }
        if (!empty($source->canceled) && $source->canceled !== '0') {
            return ['key' => 'cancelled', 'label' => 'Cancelled', 'date' => $source->canceled_date ?? null];
        }

        // Has the source piece been "ejected" into a container? store_out_*
        // rows reference store_*.id via in_id and may have a container_id.
        $outTable = $piece->source_table === 'store_sea' ? 'store_out_sea' : 'store_out_sky';
        $out = DB::table($outTable)->where('in_id', $piece->source_id)->orderBy('id', 'desc')->first();
        if ($out) {
            // If the container has a status field marking it shipped /
            // arrived, prefer that. The container schema stores everything
            // as text so we treat "arrived" / "delivered" as terminal states.
            $containerTable = $piece->source_table === 'store_sea' ? 'containers_sea' : 'containers_sky';
            $container = !empty($out->container_id)
                ? DB::table($containerTable)->where('id', $out->container_id)->first()
                : null;
            $cstatus = strtolower((string) ($container->status ?? ''));
            if (in_array($cstatus, ['arrived', 'delivered', 'received'], true)) {
                return ['key' => 'delivered', 'label' => 'Delivered', 'date' => $container->arrival ?? $out->created_date];
            }
            if (in_array($cstatus, ['shipped', 'in_transit', 'on_the_way'], true)) {
                return ['key' => 'in_transit', 'label' => 'In transit', 'date' => $out->created_date];
            }
            return ['key' => 'in_container', 'label' => 'Loaded into container', 'date' => $out->created_date];
        }

        return ['key' => 'received', 'label' => 'Received at warehouse', 'date' => $source->created_date ?? null];
    }

    /**
     * Mint a unique 12-char Crockford-base32 tracking code. Retries on the
     * (astronomically unlikely) event of a collision. The exists() check
     * pre-empts the common case; the caller still needs to handle the
     * insert-side race itself (see generateUniqueCode below).
     */
    private function mintTrackingCode(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford: no I L O U
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                $code .= $alphabet[random_int(0, 31)];
            }
            $exists = DB::table('shipment_pieces')->where('tracking_code', $code)->exists();
            if (!$exists) return $code;
        }
        throw new \RuntimeException('Could not mint a unique tracking_code after 5 attempts');
    }

    /**
     * Format a stored 12-char code for human display:
     *   "AB12CD34EF56" + prefix="SHIP"  ->  "SHIP-AB12-CD34-EF56"
     * If prefix is empty (no company name + no override), the prefix and
     * its dash are omitted.
     */
    private function prettyCode(string $code, string $prefix = ''): string
    {
        $code = strtoupper($code);
        if (strlen($code) !== 12) return $code;
        $head = $prefix !== '' ? ($prefix . '-') : '';
        return $head . substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
    }

    /**
     * Resolve the tracking prefix from settings, falling back to the
     * derived company-initials default. Always uppercase, alphanumeric,
     * capped at 5 chars (the same constraints settingsController::save()
     * enforces on write).
     */
    private function resolvePrefix(array $settings): string
    {
        $raw = (string) ($settings['tracking_prefix'] ?? '');
        if ($raw === '') {
            $raw = settingsController::deriveBrandPrefix($settings['company_name'] ?? '');
        }
        $clean = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
        return substr($clean, 0, 5);
    }

    private function requireAuth(): void
    {
        if (!auth()->user()) abort(403);
    }
}
