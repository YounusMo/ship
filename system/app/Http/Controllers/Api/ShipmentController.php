<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Enums\ShipmentMode;
use App\Modules\Tracking\Services\UnifiedTimelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shipment list + detail, scoped to the authenticated client. We pull
 * the client's pieces from store_sea / store_sky (received) UNIONed with
 * store_out_sea / store_out_sky (delivered out) so the app shows both
 * "waiting at our warehouse" and "shipped to me" shipments.
 *
 * Per the user's "use what's there" status decision, we map raw column
 * state to a small enum the app can render. The mapping lives here in
 * one place so future status logic stays consistent across the API.
 */
class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        $clientId = (int) $request->user()->id;
        $request->validate([
            'mode'     => 'nullable|in:sea,sky,all',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $mode    = $request->input('mode', 'all');
        $perPage = (int) $request->input('per_page', 25);

        $modes = $mode === 'all' ? ['sea', 'sky'] : [$mode];
        $all = collect();

        foreach ($modes as $m) {
            // store_*: received at warehouse, may or may not be in a container yet.
            $received = DB::table("store_{$m}")
                ->where('client_id', $clientId)
                ->whereNull('canceled')
                ->select([
                    'id', 'transaction_number', 'client_id',
                    'type', 'number', 'category', 'kg', 'cbm', 'notes',
                    'ship_from', 'created_date',
                    DB::raw("'$m' as mode"),
                    DB::raw("'received' as bucket"),
                ])
                ->get();

            // store_out_*: packed into a container and dispatched. payment_pending
            // is a new column (May 16 migration) that the app should surface
            // so the client knows there's a charge waiting.
            $shipped = DB::table("store_out_{$m}")
                ->where('client_id', $clientId)
                ->select([
                    'id', 'transaction_number', 'client_id', 'container_id',
                    'number', 'kg', 'cbm', 'payment_pending',
                    'created_date',
                    DB::raw("'$m' as mode"),
                    DB::raw("'shipped' as bucket"),
                ])
                ->get();

            $all = $all->concat($received)->concat($shipped);
        }

        // Sort newest first across both modes/buckets, then paginate manually
        // (we can't paginate a UNION cleanly without raw SQL).
        $sorted = $all->sortByDesc('created_date')->values();
        $page   = (int) $request->input('page', 1);
        $items  = $sorted->forPage($page, $perPage)->values();

        return response()->json([
            'data'         => $items,
            'current_page' => $page,
            'per_page'     => $perPage,
            'total'        => $sorted->count(),
        ]);
    }

    public function show(Request $request, string $mode, int $id)
    {
        $clientId = (int) $request->user()->id;
        if (!in_array($mode, ['sea', 'sky'], true)) {
            abort(404);
        }

        // Try received first, then shipped — both id-namespaces are independent.
        $row = DB::table("store_{$mode}")
            ->where('id', $id)
            ->where('client_id', $clientId)
            ->first();
        $bucket = 'received';

        if (!$row) {
            $row = DB::table("store_out_{$mode}")
                ->where('id', $id)
                ->where('client_id', $clientId)
                ->first();
            $bucket = 'shipped';
        }

        if (!$row) {
            abort(404);
        }

        // Pieces lookup uses the per-bucket table — store_* for received,
        // store_out_* for shipped — since the piece's source_table column
        // mirrors the bucket name.
        $pieceSourceTable = $bucket === 'shipped' ? "store_out_{$mode}" : "store_{$mode}";
        $pieces = DB::table('shipment_pieces')
            ->where('source_table', $pieceSourceTable)
            ->where('source_id', $row->id)
            ->orderBy('piece_index')
            ->get();

        // Unified tracking timeline is only meaningful for shipped rows
        // (received-and-not-yet-dispatched rows have no upstream tracking
        // and no internal hand-offs yet).
        $tracking = null;
        if ($bucket === 'shipped') {
            $containerId = isset($row->container_id) ? (int) $row->container_id : null;
            $tracking = app(UnifiedTimelineService::class)->for(
                ShipmentMode::from($mode),
                (int) $row->id,
                $containerId,
                null,
                $request->header('Accept-Language'),
            );
        }

        return response()->json([
            'mode'     => $mode,
            'bucket'   => $bucket,
            'row'      => $row,
            'pieces'   => $pieces,
            'tracking' => $tracking,
        ]);
    }
}
