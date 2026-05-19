<?php

namespace App\Http\Controllers;

use Illuminate\Http\UploadedFile;

abstract class Controller
{
    /** Image MIME types we accept for any user-submitted upload. */
    protected const ALLOWED_IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /** Matching extension whitelist; we always store with our own extension, never the client's filename. */
    protected const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Move an uploaded image to $destinationDir under a server-generated name.
     * Returns the stored filename, or null if the upload was rejected.
     *
     * Caller is responsible for choosing a destination dir that does not include
     * any request-supplied path segments (sanitize $client_id etc. first).
     */
    protected function storeUploadedImage(UploadedFile $file, string $destinationDir): ?string
    {
        if (!$file->isValid()) {
            return null;
        }

        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
            return null;
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
            return null;
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $file->move($destinationDir, $name);

        return $name;
    }

    /**
     * Escape SQL LIKE metacharacters in a user-supplied search term so a `%`
     * or `_` in the input does not turn a "starts with foo" filter into an
     * enumeration vector. Use at the point of assignment, not in the WHERE.
     */
    protected function escapeLike($value): string
    {
        return addcslashes((string) $value, '%_\\');
    }

    /**
     * Sanitize an integer-shaped path component (e.g. client_id) before
     * concatenating it into a filesystem path. Returns null if not a positive integer.
     */
    protected function safeIntSegment($value): ?int
    {
        if (!is_numeric($value)) return null;
        $i = (int) $value;
        return $i > 0 ? $i : null;
    }

    /**
     * Enforce that the calling staff user is allowed to act on the given client.
     * - `admin` may touch any client.
     * - `branch_admin` may only touch clients in their own branch (mirrors the
     *   list-scoping in clientsController::load, line 44).
     * - anything else (or no client found) gets a 403.
     */
    /**
     * Pull a counterparty id from the request, preferring the explicit name
     * (`client_id`/`supplier_id`/`broker_id`) and falling back to the legacy
     * `id` for backwards compatibility. Returns int or null.
     *
     * The motivation: most modules expose `id` as the primary-key parameter,
     * but `id` is also a column-name in URLs and breadcrumbs. A typo in JS
     * (or an operator typing the user-facing `code` into a URL) used to
     * create orphan rows. The existence checks added during the smoke test
     * catch that at the auth layer; this helper makes the param explicit so
     * future routes don't have to repeat the dance.
     */
    protected function counterpartyId(\Illuminate\Http\Request $request, string $explicit): ?int
    {
        $v = $request->input($explicit);
        if ($v === null || $v === '') $v = $request->input('id');
        return is_numeric($v) ? (int) $v : null;
    }

    protected function assertCanAccessClient($clientId): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized');
        }

        // Always verify the client exists. Without this admins (and bugs in
        // callers) can post mutations against orphaned ids, silently creating
        // transaction rows with no matching counterparty — those show up as
        // reconciliation drift later.
        $client = \Illuminate\Support\Facades\DB::table('clients')
            ->where('id', $clientId)
            ->where('deleted', 0)
            ->first(['branch']);

        if (!$client) {
            abort(404, 'Client not found');
        }

        if ($user->type === 'admin') {
            return;
        }

        if ($user->type !== 'branch_admin') {
            abort(403, 'Unauthorized');
        }

        if ((int) $client->branch !== (int) $user->branch) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Verify the given supplier exists (and isn't soft-deleted). Same
     * motivation as assertCanAccessClient — without this, an admin or buggy
     * caller can post against a non-existent supplier_id and the system will
     * happily create orphan rows.
     */
    protected function assertSupplierExists($supplierId): void
    {
        if (empty($supplierId) || !is_numeric($supplierId)) {
            abort(422, 'supplier_id required');
        }
        $exists = \Illuminate\Support\Facades\DB::table('suppliers')
            ->where('id', (int) $supplierId)->where('deleted', 0)->exists();
        if (!$exists) {
            abort(404, 'Supplier not found');
        }
    }

    protected function assertBrokerExists($brokerId): void
    {
        if (empty($brokerId) || !is_numeric($brokerId)) {
            abort(422, 'broker_id required');
        }
        $exists = \Illuminate\Support\Facades\DB::table('customs_brokers')
            ->where('id', (int) $brokerId)->where('deleted', 0)->exists();
        if (!$exists) {
            abort(404, 'Customs broker not found');
        }
    }

    /**
     * Validate a client-supplied `purpose` code against the allow-list for
     * its transaction type. Unknown codes (including empty / spoofed values)
     * collapse to null so we never persist garbage in the column.
     *
     * @param  mixed              $value             — usually $request->purpose
     * @param  array<string,string> $allowedCodeLabel — e.g. dataController::$client_deposit_purposes
     */
    protected function normalizePurpose($value, array $allowedCodeLabel): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        return array_key_exists($value, $allowedCodeLabel) ? $value : null;
    }

    /**
     * Issue a strictly-sequential, never-reused receipt for a money-moving
     * transaction. Returns the receipt id on success, null on failure.
     *
     * MUST be called inside the same DB::transaction as the underlying
     * business operation — if the operation rolls back, the receipt rolls
     * back with it (which is what you want — no orphan receipts).
     *
     * $payload:
     *   source_table       — e.g. 'clients_transactions'
     *   source_id          — row id in source table (use insertGetId)
     *   transaction_number — original transaction_number string
     *   auto_id            — original auto_id integer
     *   kind               — 'deposit' / 'withdraw' / 'transfer' / 'commission' / 'supplier_deposit' / 'customs_deposit'
     *   currency           — 'usd' / 'eur' / 'den' / 'cny'
     *   amount             — float
     *   counterparty_type  — 'client' / 'supplier' / 'customs_broker'
     *   counterparty_id    — int
     *   branch_id          — int (nullable)
     *   purpose            — purpose code (nullable)
     *   notes              — string (nullable)
     */
    /**
     * Reject the request if any of the given transaction dates falls in a
     * closed accounting period. Pass either a single date or several — the
     * guard fires on the FIRST closed period it finds.
     *
     * Always check today's date AND the explicit transaction date when
     * provided, so a future feature that accepts a back-dated `created_date`
     * from the client can't slip through the lock by using yesterday.
     *
     * Admins can override with `X-Override-Closed-Period: yes` (the front-end
     * gates this behind a confirmation dialog).
     */
    protected function assertPeriodOpen(string ...$dates): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('accounting_periods')) {
            return;
        }
        // Always include today so we don't accidentally allow a write into a
        // period the caller forgot to pass.
        $candidates = array_merge([date('Y-m-d')], $dates);
        // De-dupe by (year, month).
        $seen = [];
        foreach ($candidates as $d) {
            if (empty($d)) continue;
            $ts = strtotime($d);
            if ($ts === false) continue;
            $year  = (int) date('Y', $ts);
            $month = (int) date('m', $ts);
            $key   = $year . '-' . $month;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $period = \Illuminate\Support\Facades\DB::table('accounting_periods')
                ->where('period_year', $year)->where('period_month', $month)->first();
            if (!$period || $period->status !== 'closed') {
                continue;
            }
            $label = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            if (auth()->user()?->type === 'admin' && request()->header('X-Override-Closed-Period') === 'yes') {
                \Illuminate\Support\Facades\Log::warning('Admin override of closed period', [
                    'user'   => auth()->user()->id,
                    'period' => $label,
                    'route'  => request()->path(),
                ]);
                continue;
            }
            abort(response()->json([
                'type'    => 'period_closed',
                'message' => 'Accounting period ' . $label . ' is closed.',
            ], 423));
        }
    }

    /**
     * Catch-block helper. Logs the exception with a short correlation ID and
     * returns a 500 response that carries the same ID. The user sees an
     * opaque error code; the developer can grep the same code out of
     * laravel.log to find the stack trace. Far better than the previous
     * `{type:'error'}` 500 with no breadcrumb.
     */
    protected function reportException(\Throwable $th, string $context = ''): \Illuminate\Http\JsonResponse
    {
        $rid = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        \Illuminate\Support\Facades\Log::error('[req:' . $rid . '] ' . ($context ? $context . ': ' : '') . $th->getMessage(), [
            'request_id' => $rid,
            'context'    => $context,
            'route'      => request()->path(),
            'user_id'    => auth()->user()?->id,
            'exception'  => $th,
        ]);
        return response()->json([
            'type'       => 'error',
            'request_id' => $rid,
            'message'    => 'Unexpected error. Quote ID ' . $rid . ' to support.',
        ], 500);
    }

    /**
     * Map the system's plus_minus column value to a signed integer. Every
     * transaction table stores the WORD form ('plus' / 'minus'); some legacy
     * code uses the sign characters ('+' / '-'). This helper accepts both so
     * future call sites can't trip the bug the trial balance hit during the
     * smoke test.
     *
     * Returns +1 for plus, -1 for minus, 0 for anything unrecognized
     * (caller should treat 0 as "skip").
     */
    protected function signOf($plusMinus): int
    {
        $v = is_string($plusMinus) ? strtolower(trim($plusMinus)) : $plusMinus;
        if ($v === 'plus' || $v === '+' || $v === 1)   return 1;
        if ($v === 'minus' || $v === '-' || $v === -1) return -1;
        return 0;
    }

    /**
     * A received shipment is considered "locked" once the client has
     * received it — concretely: the row has been ejected into a container
     * AND that outbound entry has had a payment recorded (the operator
     * marks the handoff settled). Once locked, edits to the source
     * store_<x> row are blocked so historical records stay intact.
     *
     * Mode is 'sea' or 'sky'. Returns true if the source row is locked.
     */
    protected function shipmentSourceIsLocked(string $mode, int $sourceId): bool
    {
        $out = $mode === 'sky' ? 'store_out_sky' : 'store_out_sea';
        return \Illuminate\Support\Facades\DB::table($out)
            ->where('in_id', (string) $sourceId)
            ->where(function ($q) {
                $q->where('canceled', '!=', 'true')->orWhereNull('canceled');
            })
            ->where(function ($q) {
                $q->whereNotNull('payment')->where('payment', '!=', '');
            })
            ->exists();
    }

    /**
     * Same check but vectorised — given an iterable of source IDs return
     * a [id => true] map for the locked ones. Used by the received-table
     * view so it can disable Edit per-row without N queries.
     */
    protected function lockedShipmentIds(string $mode, array $sourceIds): array
    {
        if (empty($sourceIds)) return [];
        $out = $mode === 'sky' ? 'store_out_sky' : 'store_out_sea';
        $rows = \Illuminate\Support\Facades\DB::table($out)
            ->whereIn('in_id', array_map('strval', $sourceIds))
            ->where(function ($q) {
                $q->where('canceled', '!=', 'true')->orWhereNull('canceled');
            })
            ->where(function ($q) {
                $q->whereNotNull('payment')->where('payment', '!=', '');
            })
            ->pluck('in_id')
            ->unique()
            ->values();
        $map = [];
        foreach ($rows as $id) $map[(int) $id] = true;
        return $map;
    }

    protected function issueReceipt(array $payload): ?int
    {
        // Skip pending source rows. Issuing a sequential receipt for a
        // transaction that may still be rejected leaves orphan receipts
        // pointing at deleted rows. The approval path should call
        // issueReceipt explicitly when status flips to 'approved'.
        if (isset($payload['status']) && $payload['status'] === 'pending') {
            return null;
        }
        try {
            $db = \Illuminate\Support\Facades\DB::connection();

            // Resolve counterparty label/code so the receipt is a frozen
            // snapshot even if the client/supplier is renamed or deleted later.
            $label = null; $code = null;
            if (!empty($payload['counterparty_type']) && !empty($payload['counterparty_id'])) {
                $table = match ($payload['counterparty_type']) {
                    'client'         => 'clients',
                    'supplier'       => 'suppliers',
                    'customs_broker' => 'customs_brokers',
                    default          => null,
                };
                if ($table) {
                    $cp = $db->table($table)->where('id', $payload['counterparty_id'])->first();
                    if ($cp) {
                        $label = $cp->name ?? null;
                        $code  = $cp->code ?? null;
                    }
                }
            }

            $user = auth()->user();
            $series = 'A';

            // Lock the max number for this series to avoid two parallel
            // requests grabbing the same number. MySQL's atomic select
            // FOR UPDATE inside the surrounding DB::transaction is enough.
            $maxRow = $db->table('receipts')
                ->where('series_letter', $series)
                ->lockForUpdate()
                ->max('series_number');
            $nextNumber = ((int) $maxRow) + 1;

            return $db->table('receipts')->insertGetId([
                'series_letter'        => $series,
                'series_number'        => $nextNumber,
                'source_table'         => $payload['source_table'],
                'source_id'            => is_numeric($payload['source_id'] ?? null) ? (int) $payload['source_id'] : 0,
                'transaction_number'   => $payload['transaction_number'] ?? null,
                'auto_id'              => is_numeric($payload['auto_id'] ?? null) ? (int) $payload['auto_id'] : null,
                'kind'                 => $payload['kind'],
                'currency'             => $payload['currency'] ?? null,
                'amount'               => isset($payload['amount']) ? floatval($payload['amount']) : null,
                'counterparty_type'    => $payload['counterparty_type'] ?? null,
                'counterparty_id'      => is_numeric($payload['counterparty_id'] ?? null) ? (int) $payload['counterparty_id'] : null,
                'counterparty_label'   => $label,
                'counterparty_code'    => $code,
                'branch_id'            => is_numeric($payload['branch_id'] ?? null) ? (int) $payload['branch_id'] : null,
                'purpose'              => $payload['purpose'] ?? null,
                'notes'                => $payload['notes'] ?? null,
                'issued_by_user_id'    => $user?->id,
                'issued_by_user_name'  => $user?->name,
                'issued_at'            => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'issueReceipt failed: ' . $e->getMessage(),
                ['payload' => $payload]
            );
            return null;
        }
    }

    /**
     * Append a row to audit_log. Failure of the audit insert is logged but
     * NEVER propagated — we will not let a logging hiccup break a payroll
     * deposit.
     *
     * Call from inside the same DB::transaction as the change being recorded
     * so a rolled-back business operation also rolls back its audit row.
     *
     * Use a stable verb for $action ("deposit", "withdraw", "transfer",
     * "transaction_delete", "password_change", "role_change", …). The
     * audit-log viewer groups by it.
     */
    protected function logAudit(
        string $action,
        string $targetTable,
        $targetId = null,
        ?array $payload = null,
        ?string $context = null
    ): void {
        try {
            $user = auth()->user();
            \Illuminate\Support\Facades\DB::table('audit_log')->insert([
                'user_id'      => $user?->id,
                'user_type'    => $user?->type,
                'branch_id'    => $user?->branch ?? null,
                'action'       => $action,
                'target_table' => $targetTable,
                'target_id'    => is_numeric($targetId) ? (int) $targetId : null,
                'payload'      => $payload !== null
                    ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
                    : null,
                'ip'           => request()?->ip(),
                'context'      => $context !== null ? mb_substr($context, 0, 191) : null,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'audit_log insert failed: ' . $e->getMessage(),
                ['action' => $action, 'target' => $targetTable . ':' . $targetId]
            );
        }
    }
}
