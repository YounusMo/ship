<?php

declare(strict_types=1);

namespace App\Modules\Purchases\StateMachine\Guards;

use App\Modules\Purchases\Exceptions\ExchangeRateException;
use App\Modules\Purchases\Exceptions\InsufficientBalanceException;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Services\ExchangeRateService;
use App\Modules\Purchases\Services\WalletIntegrationService;
use App\Modules\Purchases\StateMachine\TransitionGuardInterface;
use App\Modules\Purchases\Support\MoneyHelper;

/**
 * Guard للانتقال PENDING_CONFIRMATION → CONFIRMED
 *
 * يتحقق من:
 * 1. وجود سعر صرف نشط للعملة
 * 2. كفاية رصيد العميل
 * 3. وجود بنود في الطلب
 * 4. صحة بيانات العمولة
 */
class ConfirmGuard implements TransitionGuardInterface
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
        private readonly WalletIntegrationService $walletService,
    ) {
    }

    public function check(PurchaseOrder $order, array $context): void
    {
        // 1. وجود بنود
        if ($order->items()->count() === 0) {
            throw new \App\Modules\Purchases\Exceptions\PurchaseException(
                message: 'Order has no items',
                messageAr: 'الطلب فارغ، يجب إضافة بنود قبل التأكيد',
            );
        }

        // 2. سعر صرف نشط (لو عملة الشراء != عملة العميل)
        if ($order->purchase_currency !== $order->customer_currency) {
            $rate = $this->exchangeRateService->getCurrentRate(
                $order->customer_currency,
                $order->purchase_currency,
            );

            if ($rate === null) {
                throw ExchangeRateException::notAvailable(
                    $order->customer_currency,
                    $order->purchase_currency,
                );
            }
        }

        // 3. رصيد العميل كافي
        $estimatedTotal = (string) $order->estimated_total_usd;
        $available = $this->walletService->getAvailableBalance($order->customer_id);

        if (MoneyHelper::lt($available, $estimatedTotal)) {
            throw InsufficientBalanceException::forCustomer(
                required: $estimatedTotal,
                available: $available,
                currency: 'USD',
            );
        }

        // 4. لو العمولة NONE، لازم notes موجود
        if (
            $order->commission_type->value === 'NONE'
            && empty($order->commission_notes)
        ) {
            throw new \App\Modules\Purchases\Exceptions\PurchaseException(
                message: 'Commission notes required when type is NONE',
                messageAr: 'ملاحظات العمولة إجبارية عند اختيار "بدون عمولة"',
                errorCode: 'MISSING_COMMISSION_NOTES',
            );
        }
    }
}
