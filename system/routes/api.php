<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\NotificationController;

/*
 * Mobile-client API surface. All routes are auto-prefixed with /api by
 * Laravel's bootstrap/app.php withRouting(api: ...) wiring.
 *
 * Auth: Sanctum personal-access-tokens issued by /api/auth/login. Every
 * authenticated route is guarded by both auth:sanctum (resolves the
 * tokenable) AND client.sanctum (narrows to the Client model so a staff
 * PAT can't hit these).
 *
 * Rate-limiting on login is handled per-identifier inside AuthController
 * (mirrors the web flow). Throttle:60,1 on the open login route keeps
 * the unauthenticated bucket bounded too.
 */

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:60,1');

Route::middleware(['auth:sanctum', 'client.sanctum'])->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me',           [AuthController::class, 'me']);

    Route::post('/devices/register', [DeviceController::class, 'register']);
    Route::post('/devices/revoke',   [DeviceController::class, 'revoke']);
    Route::get('/devices',           [DeviceController::class, 'index']);
    Route::post('/devices/{id}/revoke', [DeviceController::class, 'revokeById'])
        ->where('id', '[0-9]+');

    Route::get('/balances',     BalanceController::class);
    Route::get('/transactions', [TransactionController::class, 'index']);

    Route::get('/shipments',                ShipmentController::class . '@index');
    Route::get('/shipments/{mode}/{id}',    ShipmentController::class . '@show')
        ->where('mode', 'sea|sky')
        ->where('id', '[0-9]+');

    Route::get('/receipts', [ReceiptController::class, 'index']);

    Route::get('/notifications',                 [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read',      [NotificationController::class, 'markRead'])
        ->where('id', '[0-9a-f\-]+');
    Route::post('/notifications/read-all',       [NotificationController::class, 'markAllRead']);
    Route::get('/notifications/prefs',           [NotificationController::class, 'getPrefs']);
    Route::patch('/notifications/prefs',         [NotificationController::class, 'updatePrefs']);
});

/*
 * Operator-facing Purchases module — staff use only, NOT customers, so it
 * lives outside the client.sanctum group. routes/purchases.php declares
 * its own auth:sanctum middleware. See app/Modules/Purchases.
 */
require __DIR__ . '/purchases.php';

/*
 * Tracking module — webhooks (open, signature-verified) + employee API
 * (added in Phase 5). See app/Modules/Tracking.
 */
require __DIR__ . '/tracking.php';
