<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delete failed_jobs older than the retention window.
 *
 * Laravel ships `queue:flush` but that flushes the entire table. We
 * want the recent failures kept around so the team can triage them.
 *
 * Run weekly. See docs/GAPS.md gap #8.
 */
class PurgeFailedJobs extends Command
{
    protected $signature = 'purge:failed-jobs
                            {--days=30 : Delete failures older than this many days.}
                            {--dry-run : Show what would be deleted without writing.}';

    protected $description = 'Delete failed_jobs rows past the retention window.';

    public function handle(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            $this->info('failed_jobs table not present — nothing to do.');
            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = DB::table('failed_jobs')->where('failed_at', '<', $cutoff);
        $count = (int) $query->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} failed job(s) older than {$days} days.");
            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Deleted {$deleted} failed job(s) older than {$days} days.");
        return self::SUCCESS;
    }
}
