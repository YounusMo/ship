<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tracking\Services\ShipsGo\Exceptions\ShipsGoApiException;
use App\Modules\Tracking\Services\ShipsGo\ShipsGoClient;
use Illuminate\Console\Command;

/**
 * Operator-run smoke test for ShipsGo credentials.
 *
 *   php artisan tracking:shipsgo-smoke
 *   php artisan tracking:shipsgo-smoke --reference=ACME12345
 *
 * Without --reference, just hits /account/credits to confirm the API key
 * is accepted. With --reference, also fetches one ocean shipment.
 */
class ShipsGoSmokeCommand extends Command
{
    protected $signature = 'tracking:shipsgo-smoke
                            {--reference= : Optional shipment reference to GET}
                            {--mode=ocean : ocean|air}';

    protected $description = 'Validate ShipsGo API credentials and endpoint reachability.';

    public function handle(ShipsGoClient $client): int
    {
        $apiKey = (string) config('tracking.shipsgo.api_key');
        if ($apiKey === '') {
            $this->error('SHIPSGO_API_KEY is not set in .env — nothing to smoke-test.');
            return self::FAILURE;
        }

        $this->info('Checking ShipsGo credits endpoint...');
        $credits = $client->getCredits();
        if ($credits === null) {
            $this->warn('Credits endpoint unreachable or returned no number.');
        } else {
            $this->line("Remaining credits: <fg=green>{$credits}</>");
        }

        $reference = $this->option('reference');
        if ($reference === null) {
            $this->line('Done. Re-run with --reference=SHIPMENT_ID to fetch a real shipment.');
            return self::SUCCESS;
        }

        $mode = (string) $this->option('mode');
        $this->info("Fetching {$mode} shipment {$reference} ...");

        try {
            $payload = match ($mode) {
                'air'   => $client->getAirShipment((string) $reference),
                default => $client->getOceanShipment((string) $reference),
            };
        } catch (ShipsGoApiException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line('Top-level keys: ' . implode(', ', array_keys($payload)));
        return self::SUCCESS;
    }
}
