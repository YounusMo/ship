<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tracking\Services\ShipsGo\Exceptions\ShipsGoApiException;
use App\Modules\Tracking\Services\ShipsGo\ShipsGoClient;
use Illuminate\Console\Command;

/**
 *   php artisan tracking:shipsgo-smoke --shipment=1001
 *   php artisan tracking:shipsgo-smoke --shipment=1001 --mode=air
 *
 * Operator-run smoke test for ShipsGo credentials + endpoint reachability.
 *
 * ShipsGo v2 doesn't expose a credit-balance endpoint — credit exhaustion
 * is signaled via HTTP 402 NOT_ENOUGH_CREDITS on create endpoints — so the
 * smoke command requires --shipment=<id> to actually exercise auth. Without
 * an id we can only confirm a key is configured.
 */
class ShipsGoSmokeCommand extends Command
{
    protected $signature = 'tracking:shipsgo-smoke
                            {--shipment= : Shipment id (integer) to fetch}
                            {--mode=ocean : ocean|air}';

    protected $description = 'Validate ShipsGo API credentials by fetching one shipment.';

    public function handle(ShipsGoClient $client): int
    {
        $apiKey = (string) config('tracking.shipsgo.api_key');
        if ($apiKey === '') {
            $this->error('SHIPSGO_API_KEY is not set in .env — nothing to smoke-test.');
            return self::FAILURE;
        }

        $this->line('SHIPSGO_API_KEY is configured.');
        $this->line('Base URL: ' . config('tracking.shipsgo.base_url'));

        $shipmentId = $this->option('shipment');
        if ($shipmentId === null) {
            $this->warn('No --shipment=<id> given — cannot verify the key works end-to-end.');
            $this->line('Re-run with --shipment=<id> (and optionally --mode=air) to fetch a real shipment.');
            return self::SUCCESS;
        }

        $mode = (string) $this->option('mode');
        $this->info("Fetching {$mode} shipment #{$shipmentId} ...");

        try {
            $payload = match ($mode) {
                'air'   => $client->getAirShipment((string) $shipmentId),
                default => $client->getOceanShipment((string) $shipmentId),
            };
        } catch (ShipsGoApiException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line('Response top-level keys: ' . implode(', ', array_keys($payload)));
        if (isset($payload['shipment']['status'])) {
            $this->line('Shipment status: <fg=green>' . $payload['shipment']['status'] . '</>');
        }
        return self::SUCCESS;
    }
}
