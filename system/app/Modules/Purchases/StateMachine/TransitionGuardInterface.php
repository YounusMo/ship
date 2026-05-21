<?php

declare(strict_types=1);

namespace App\Modules\Purchases\StateMachine;

use App\Modules\Purchases\Models\PurchaseOrder;

/**
 * Interface للـ Guards (تحقق من الشروط قبل الانتقال)
 *
 * @example: ConfirmGuard يتحقق من رصيد العميل وتوفر سعر صرف
 */
interface TransitionGuardInterface
{
    /**
     * تحقق من الشروط، يرمي exception لو فشلت
     *
     * @param  array<string, mixed>  $context
     * @throws \App\Modules\Purchases\Exceptions\PurchaseException
     */
    public function check(PurchaseOrder $order, array $context): void;
}
