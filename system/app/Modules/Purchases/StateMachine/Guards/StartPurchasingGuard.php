<?php

declare(strict_types=1);

namespace App\Modules\Purchases\StateMachine\Guards;

use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\StateMachine\TransitionGuardInterface;

/**
 * Guard للانتقال CONFIRMED → PURCHASING
 *
 * يتحقق من:
 * - وجود مسؤول مشتريات مُعيّن (buyer_id != null)
 * - المسؤول نشط
 * - رصيد العُهدة كافي (estimated_total_usd <= buyer.balance)
 * - قيمة الطلب لا تتجاوز buyer.max_order_value
 *
 * @todo: نفّذ المنطق
 */
class StartPurchasingGuard implements TransitionGuardInterface
{
    public function check(PurchaseOrder $order, array $context): void
    {
        if ($order->buyer_id === null) {
            throw new \App\Modules\Purchases\Exceptions\PurchaseException(
                message: 'Order has no assigned buyer',
                messageAr: 'لا يمكن بدء الشراء قبل تعيين مسؤول مشتريات',
            );
        }

        // TODO: تحقق من حالة المسؤول
        // TODO: تحقق من رصيد العُهدة
        // TODO: تحقق من max_order_value
    }
}
