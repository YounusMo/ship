<?php

declare(strict_types=1);

use App\Modules\Purchases\Http\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Purchases Module API Routes
|--------------------------------------------------------------------------
|
| يُحمَّل من PurchasesServiceProvider
| Prefix: /api/purchases
| Middleware: auth:sanctum
|
| @see CLAUDE.md Section 8 - API Endpoints
*/

Route::prefix('purchases')
    ->middleware(['auth:sanctum'])
    ->name('purchases.')
    ->group(function () {
        // ─── Purchase Orders ───────────────────────────────────────
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
        Route::get('/{order}', [PurchaseOrderController::class, 'show'])->name('show');

        // State transitions
        Route::post('/{order}/confirm', [PurchaseOrderController::class, 'confirm'])
            ->name('confirm');
        Route::post('/{order}/assign-buyer', [PurchaseOrderController::class, 'assignBuyer'])
            ->name('assign-buyer');
        Route::post('/{order}/start-purchasing', [PurchaseOrderController::class, 'startPurchasing'])
            ->name('start-purchasing');
        Route::post('/{order}/mark-purchased', [PurchaseOrderController::class, 'markPurchased'])
            ->name('mark-purchased');
        Route::post('/{order}/mark-received', [PurchaseOrderController::class, 'markReceived'])
            ->name('mark-received');
        Route::post('/{order}/add-to-shipment', [PurchaseOrderController::class, 'addToShipment'])
            ->name('add-to-shipment');
        Route::post('/{order}/mark-delivered', [PurchaseOrderController::class, 'markDelivered'])
            ->name('mark-delivered');
        Route::post('/{order}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->name('cancel');

        // TODO Claude Code: أضف باقي endpoints حسب CLAUDE.md Section 8
        // - /buyers/* (إدارة المسؤولين)
        // - /buyers/{id}/deposit, /top-up, /transfer, /reconcile
        // - /exchange-rates/* (إدارة الأسعار)
        // - /exchange-rates/manual-override, /approve, /reject
        // - /reports/*
    });
