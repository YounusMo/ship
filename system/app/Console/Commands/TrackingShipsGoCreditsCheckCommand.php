<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tracking\Services\ShipsGo\ShipsGoClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 *   php artisan tracking:shipsgo-credits-check
 *
 * Daily cron. Hits ShipsGo /account/credits and warns when remaining
 * credits dip below tracking.shipsgo.credit_low_threshold. Logs a
 * structured warning so the alerting layer (anything tailing the log)
 * can page ops.
 */
class TrackingShipsGoCreditsCheckCommand extends Command
{
    protected $signature = 'tracking:shipsgo-credits-check';

    protected $description = 'Warn if remaining ShipsGo credits drop below the configured threshold.';

    public function handle(ShipsGoClient $client): int
    {
        if (! config('tracking.shipsgo.api_key')) {
            $this->warn('SHIPSGO_API_KEY not set — skipping.');
            return self::SUCCESS;
        }

        $credits = $client->getCredits();
        if ($credits === null) {
            $this->error('Could not fetch credits.');
            Log::warning('shipsgo_credits_check_failed');
            return self::FAILURE;
        }

        $threshold = (int) config('tracking.shipsgo.credit_low_threshold', 50);
        $this->line("ShipsGo credits remaining: <fg=green>{$credits}</> (threshold {$threshold}).");

        if ($credits < $threshold) {
            $this->warn("Below threshold — alerting ops.");
            Log::warning('shipsgo_credits_low', [
                'credits'   => $credits,
                'threshold' => $threshold,
            ]);
        }

        return self::SUCCESS;
    }
}
