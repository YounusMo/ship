<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Providers;

use Illuminate\Support\ServiceProvider;

class TrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            \App\Modules\Tracking\Services\ShipsGo\ShipsGoClient::class,
            function ($app) {
                return new \App\Modules\Tracking\Services\ShipsGo\ShipsGoClient(
                    baseUrl: (string) config('tracking.shipsgo.base_url'),
                    apiKey: (string) config('tracking.shipsgo.api_key'),
                    timeoutSeconds: (int) config('tracking.shipsgo.timeout', 15),
                    retryAttempts: (int) config('tracking.shipsgo.retry_attempts', 3),
                    retryBaseMs: (int) config('tracking.shipsgo.retry_base_ms', 500),
                );
            },
        );

        $this->app->singleton(
            \App\Modules\Tracking\Services\ShipsGo\ShipsGoWebhookVerifier::class,
            function ($app) {
                return new \App\Modules\Tracking\Services\ShipsGo\ShipsGoWebhookVerifier(
                    secret: (string) config('tracking.shipsgo.webhook_secret'),
                );
            },
        );
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../../../config/tracking.php',
            'tracking',
        );

        // Translations land in Phase 5 alongside the customer-facing API.
    }
}
