<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Export old audit_log rows to disk and then delete them from the
 * primary table. The disk artifact is one gzipped JSONL file per
 * calendar month, written under `storage/app/audit-archive/`.
 *
 * Default window: 18 months. Ops should sync the resulting files to
 * cold storage (S3 Glacier / B2 Archive) as part of the nightly
 * backup. Rotation policy is to keep at least 7 years of archives
 * for tax / compliance reasons.
 *
 * Run monthly. See docs/GAPS.md gap #8.
 */
class ArchiveAuditLog extends Command
{
    protected $signature = 'archive:audit-log
                            {--months=18 : Archive rows older than this many months.}
                            {--chunk=2000 : Rows per export batch.}
                            {--dry-run : Show what would be archived without writing.}';

    protected $description = 'Export old audit_log rows to gzipped JSONL and delete.';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $chunk  = max(100, (int) $this->option('chunk'));
        $cutoff = now()->subMonths($months);

        // Reads use the default 'mysql' connection (SELECT-only on
        // audit_log is fine for the main app user). DELETE goes through
        // the connection named by config('audit.archive_connection').
        // In production this should be 'audit_admin' — a separate DB
        // user with DELETE on audit_log — so a compromised app user
        // cannot remove audit rows. In dev/CI it defaults to 'mysql'.
        // See config/audit.php and docs/MANUAL.md §25.9.
        $readConn = 'mysql';
        $writeConn = (string) config('audit.archive_connection', 'mysql');

        $base = DB::connection($readConn)->table('audit_log')
            ->where('created_at', '<', $cutoff);
        $count = (int) $base->count();

        if ($count === 0) {
            $this->info("No audit rows older than {$months} months. Nothing to do.");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would archive {$count} audit row(s) older than {$months} months.");
            return self::SUCCESS;
        }

        // Disk: storage/app/audit-archive/{yyyy-mm}.jsonl.gz
        $disk = Storage::disk('local');
        $disk->makeDirectory('audit-archive');

        // Group by year-month for tidier archives.
        $handles = [];   // year-month => gzopen handle
        $archived = 0;

        $base->orderBy('id')->chunkById($chunk, function ($rows) use (&$handles, &$archived, $disk) {
            foreach ($rows as $row) {
                $stamp = (string) $row->created_at;
                $ym = substr($stamp, 0, 7);  // 'YYYY-MM'
                if (! isset($handles[$ym])) {
                    $path = $disk->path("audit-archive/{$ym}.jsonl.gz");
                    // 'a9' = append, gzip compression level 9.
                    $handle = gzopen($path, 'a9');
                    if ($handle === false) {
                        throw new \RuntimeException("Failed to open gzip handle: {$path}");
                    }
                    $handles[$ym] = $handle;
                }
                gzwrite($handles[$ym], json_encode((array) $row, JSON_UNESCAPED_UNICODE) . "\n");
                $archived++;
            }
        });

        foreach ($handles as $h) {
            gzclose($h);
        }

        // Delete after successful write. Use chunked delete so we don't
        // lock the table for the duration. Goes through the privileged
        // connection — the main app DB user is intentionally denied
        // DELETE on audit_log in production.
        $deleted = 0;
        do {
            $batch = DB::connection($writeConn)->table('audit_log')
                ->where('created_at', '<', $cutoff)
                ->limit($chunk)
                ->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Archived {$archived} row(s) across " . count($handles) . " month-file(s); deleted {$deleted} row(s).");
        return self::SUCCESS;
    }
}
