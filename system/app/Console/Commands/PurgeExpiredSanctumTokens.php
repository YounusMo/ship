<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Delete personal_access_tokens whose expires_at is in the past.
 *
 * Run nightly. Once registered in the Laravel scheduler (see gap #1),
 * this becomes part of the routine cleanup. Until then, schedule via
 * cron:
 *
 *   0 3 * * * cd /path/to/system && php artisan tokens:purge-expired
 *
 * @see docs/GAPS.md gap #4 and #8
 */
class PurgeExpiredSanctumTokens extends Command
{
    protected $signature = 'tokens:purge-expired
                            {--dry-run : Show what would be deleted without deleting.}';

    protected $description = 'Delete Sanctum personal access tokens past their expires_at.';

    public function handle(): int
    {
        $query = DB::table('personal_access_tokens')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        $count = (int) $query->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$count} expired token(s).");
            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Deleted {$deleted} expired token(s).");
        return self::SUCCESS;
    }
}
