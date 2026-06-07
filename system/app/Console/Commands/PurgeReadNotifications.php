<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Delete already-read notifications older than the retention window.
 *
 * Unread notifications are always kept — they represent work the user
 * hasn't seen. Once `read_at` is non-null AND the row is old enough, the
 * mobile UI doesn't surface it anymore, so the DB row is just storage.
 *
 * Run weekly. See docs/GAPS.md gap #8.
 */
class PurgeReadNotifications extends Command
{
    protected $signature = 'purge:read-notifications
                            {--days=180 : Delete read notifications older than this many days.}
                            {--dry-run : Show what would be deleted without writing.}';

    protected $description = 'Delete read notifications past the retention window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('read_at', '<', $cutoff);

        $count = (int) $query->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} read notification(s) older than {$days} days.");
            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Deleted {$deleted} read notification(s) older than {$days} days.");
        return self::SUCCESS;
    }
}
