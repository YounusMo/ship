<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Providers;

use App\Modules\Purchases\Jobs\CheckLowBalancesJob;
use App\Modules\Purchases\Jobs\FetchExchangeRatesJob;
use App\Modules\Purchases\Services\ExchangeRateFetcherService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PurchasesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ExchangeRateFetcherService
        $this->app->singleton(ExchangeRateFetcherService::class, function ($app) {
            return new ExchangeRateFetcherService(
                oxrApiKey: (string) config('purchases.exchange_rates.openexchangerates.api_key'),
                oxrBaseUrl: (string) config(
                    'purchases.exchange_rates.openexchangerates.base_url',
                    'https://openexchangerates.org/api',
                ),
                frankfurterBaseUrl: (string) config(
                    'purchases.exchange_rates.frankfurter.base_url',
                    'https://api.frankfurter.app',
                ),
                timeout: (int) config('purchases.exchange_rates.timeout', 10),
            );
        });
    }

    public function boot(): void
    {
        // Config
        $this->mergeConfigFrom(__DIR__ . '/../../../../config/purchases.php', 'purchases');

        // Routes are required from routes/api.php so they inherit the
        // existing auth:sanctum + client.sanctum group rather than running
        // unprotected. See routes/api.php.

        // Migrations live in the unified database/migrations dir that
        // Laravel already discovers — no loadMigrationsFrom needed.

        // Translations (Laravel 12 convention: lang/ at project root).
        $this->loadTranslationsFrom(__DIR__ . '/../../../../lang', 'purchases');

        // Schedule jobs
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            if (config('purchases.features.auto_rate_update', true)) {
                $schedule->job(new FetchExchangeRatesJob())
                    ->everySixHours()
                    ->name('purchases:fetch-exchange-rates')
                    ->onOneServer();
            }

            $schedule->job(new CheckLowBalancesJob())
                ->dailyAt('09:00')
                ->name('purchases:check-low-balances')
                ->onOneServer();
        });
    }
}
