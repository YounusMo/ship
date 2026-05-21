<?php

declare(strict_types=1);

use App\Modules\Tracking\Http\Controllers\Employee\ActivityController;
use App\Modules\Tracking\Http\Controllers\Employee\AuthController as EmployeeAuthController;
use App\Modules\Tracking\Http\Controllers\Employee\BranchQueueController;
use App\Modules\Tracking\Http\Controllers\Employee\MeController;
use App\Modules\Tracking\Http\Controllers\Employee\ScanController;
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
| inside the controller is the security boundary.
|
| Employee endpoints sit behind auth:sanctum + employee.sanctum (narrows
| to User tokenable with 'employee' ability). branch.scope is per-route
| because some endpoints (me, activity) aren't branch-bound.
*/

Route::prefix('v1/webhooks')->group(function () {
    Route::post('/shipsgo', ShipsGoWebhookController::class)
        ->name('tracking.webhooks.shipsgo');
});

Route::prefix('v1/employee')->group(function () {
    Route::post('/auth/login', [EmployeeAuthController::class, 'login'])
        ->middleware('throttle:60,1')
        ->name('tracking.employee.login');

    Route::middleware(['auth:sanctum', 'employee.sanctum', 'mobile.sanitize'])->group(function () {
        Route::post('/auth/logout', [EmployeeAuthController::class, 'logout'])
            ->name('tracking.employee.logout');
        Route::get('/me', MeController::class)
            ->name('tracking.employee.me');

        Route::post('/scan/resolve', [ScanController::class, 'resolve'])
            ->name('tracking.employee.scan.resolve');
        Route::post('/scan/submit', [ScanController::class, 'submit'])
            ->middleware('branch.scope')
            ->name('tracking.employee.scan.submit');

        Route::get('/branches/{branch}/queue', BranchQueueController::class)
            ->middleware('branch.scope')
            ->where('branch', '[0-9]+')
            ->name('tracking.employee.branches.queue');

        Route::get('/activity', ActivityController::class)
            ->name('tracking.employee.activity');
    });
});
