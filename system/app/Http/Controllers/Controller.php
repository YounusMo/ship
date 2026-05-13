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
