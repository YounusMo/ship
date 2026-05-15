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
    protected function assertCanAccessClient($clientId): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized');
        }

        if ($user->type === 'admin') {
            return;
        }

        if ($user->type !== 'branch_admin') {
            abort(403, 'Unauthorized');
        }

        $client = \Illuminate\Support\Facades\DB::table('clients')
            ->where('id', $clientId)
            ->first(['branch']);

        if (!$client || (int) $client->branch !== (int) $user->branch) {
            abort(403, 'Unauthorized');
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
    protected function issueReceipt(array $payload): ?int
    {
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
