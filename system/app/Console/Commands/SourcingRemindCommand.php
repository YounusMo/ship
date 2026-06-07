<?php

namespace App\Console\Commands;

use App\Http\Controllers\sourcingController;
use Illuminate\Console\Command;

/**
 * Run the proforma auto-reminder pass.
 *
 *   php artisan sourcing:remind                 (defaults: 3 day age, 5 day cooldown)
 *   php artisan sourcing:remind --min-age=5 --cooldown=7 --limit=100
 *
 * Cron line for daily 9am UTC:
 *   0 9 * * * cd /path/to/system && php artisan sourcing:remind >> /var/log/sourcing-remind.log 2>&1
 */
class SourcingRemindCommand extends Command
{
    protected $signature = 'sourcing:remind
        {--min-age=3  : Only nudge proformas sent at least this many days ago}
        {--cooldown=5 : Skip proformas reminded within the last N days}
        {--limit=50   : Max proformas to process per run}';

    protected $description = 'Send auto-reminders for unanswered proformas.';

    public function handle(): int
    {
        $minAge   = (int) $this->option('min-age');
        $cooldown = (int) $this->option('cooldown');
        $limit    = (int) $this->option('limit');

        $this->info("Running sourcing reminder: min-age={$minAge}d cooldown={$cooldown}d limit={$limit}");
        $stats = (new sourcingController())->runRemindersImpl($minAge, $cooldown, $limit);

        $this->info(sprintf(
            'scanned=%d sent=%d skipped=%d failed=%d',
            $stats['scanned'] ?? 0,
            $stats['sent']    ?? 0,
            $stats['skipped'] ?? 0,
            $stats['failed']  ?? 0
        ));
        return self::SUCCESS;
    }
}
