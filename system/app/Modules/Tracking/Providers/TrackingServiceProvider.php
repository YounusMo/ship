<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Providers;

use Illuminate\Support\ServiceProvider;

class TrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Service singletons land here in Phase 2b/3 (ShipsGoClient,
        // UnifiedTimelineService, etc.).
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
