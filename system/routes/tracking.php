<?php

declare(strict_types=1);

use App\Modules\Tracking\Http\Controllers\ShipsGoWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tracking module routes
|--------------------------------------------------------------------------
|
| Required from routes/api.php so everything ends up under /api.
|
| Webhooks are unauthenticated by middleware — signature verification
| inside the controller is the security boundary. Employee routes (added
| in Phase 5) carry auth:sanctum + branch-scope middleware.
*/

Route::prefix('v1/webhooks')->group(function () {
    Route::post('/shipsgo', ShipsGoWebhookController::class)
        ->name('tracking.webhooks.shipsgo');
});
