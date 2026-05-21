<?php

declare(strict_types=1);

namespace App\Modules\Purchases\StateMachine\Effects;

use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Services\ExchangeRateService;
use App\Modules\Purchases\Services\WalletIntegrationService;
use App\Modules\Purchases\StateMachine\TransitionEffectInterface;

/**
 * Effect للانتقال PENDING_CONFIRMATION → CONFIRMED
 *
 * يقوم بـ:
 * 1. تجميد سعر الصرف الحالي على الطلب
 * 2. حجز المبلغ من محفظة العميل
 *
 * ⚠️ السعر المُجمّد لا يتغير أبداً بعد ذلك
 */
class ConfirmEffect implements TransitionEffectInterface
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
        private readonly WalletIntegrationService $walletService,
    ) {
    }

    public function execute(PurchaseOrder $order, array $context): void
    {
        // 1. تجميد سعر الصرف
        if ($order->purchase_currency !== $order->customer_currency) {
            $activeRate = $this->exchangeRateService->getActiveRate(
                $order->customer_currency,
                $order->purchase_currency,
            );

            $order->exchange_rate_id = $activeRate->id;
            $order->frozen_exchange_rate = $activeRate->effective_rate;
            $order->exchange_rate_frozen_at = now();
        }

        // 2. حجز المبلغ من محفظة العميل
        $this->walletService->reserveAmount(
            customerId: $order->customer_id,
            amount: (string) $order->estimated_total_usd,
            currency: 'USD',
            purchaseOrder: $order,
            reservationRef: "PO-{$order->id}",
        );

        $order->save();
    }
}
