<?php

namespace App\Console\Commands;

use App\Http\Controllers\sourcingController;
use Illuminate\Console\Command;

/**
 * Phase 15 — daily deal-health snapshot.
 *
 *   php artisan sourcing:health-snapshot                  (default 60d retention)
 *   php artisan sourcing:health-snapshot --retain=30
 *
 * Cron line for nightly 01:00 UTC:
 *   0 1 * * * cd /path/to/system && php artisan sourcing:health-snapshot >> /var/log/sourcing-health.log 2>&1
 */
class SourcingHealthSnapshotCommand extends Command
{
    protected $signature = 'sourcing:health-snapshot
        {--retain=60 : Drop snapshots older than N days from today}';

    protected $description = 'Capture today\'s deal-health score for every active proforma.';

    public function handle(): int
    {
        $retain = max(7, (int) $this->option('retain'));

        $this->info("Running sourcing health snapshot: retain={$retain}d");
        $stats = (new sourcingController())->runHealthSnapshotImpl($retain);

        $this->info(sprintf(
            'scanned=%d snapshotted=%d failed=%d',
            $stats['scanned'] ?? 0,
            $stats['snapshotted'] ?? 0,
            $stats['failed'] ?? 0
        ));

        return ($stats['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
