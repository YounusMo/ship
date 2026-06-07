<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Trim webhook payloads beyond the retention window.
 *
 * We keep the metadata rows forever (cheap, useful for audit), but the
 * `payload` JSON can be substantial for carriers like ShipsGo that
 * include full shipment snapshots on every event. This command nullifies
 * `payload` on rows whose `received_at` is older than the configured
 * window, freeing storage while preserving the audit trail.
 *
 * Run nightly. See docs/GAPS.md gap #8.
 */
class PurgeWebhookPayloads extends Command
{
    protected $signature = 'purge:webhook-payloads
                            {--days=90 : Trim payloads older than this many days.}
                            {--dry-run : Show what would be trimmed without writing.}';

    protected $description = 'Replace webhook_deliveries.payload with {} past the retention window.';

    /**
     * Stub value substituted for the original payload. NOT_NULL on
     * the column means we can't NULL it; the stub keeps the column
     * valid while freeing the JSON bulk.
     */
    private const STUB = '{"_trimmed": true}';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = DB::table('webhook_deliveries')
            ->where('received_at', '<', $cutoff)
            ->where('payload', '!=', self::STUB);

        $count = (int) $query->count();

        if ($this->option('dry-run')) {
            $this->info("Would trim {$count} payload(s) older than {$days} days.");
            return self::SUCCESS;
        }

        $affected = $query->update([
            'payload'    => self::STUB,
            'updated_at' => now(),
        ]);
        $this->info("Trimmed {$affected} payload(s) older than {$days} days.");
        return self::SUCCESS;
    }
}
