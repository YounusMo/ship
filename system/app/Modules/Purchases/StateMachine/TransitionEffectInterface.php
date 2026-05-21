<?php

declare(strict_types=1);

namespace App\Modules\Purchases\StateMachine;

use App\Modules\Purchases\Models\PurchaseOrder;

/**
 * Interface للـ Effects (التأثيرات الجانبية بعد الانتقال)
 *
 * @example: ConfirmEffect يحجز المبلغ من محفظة العميل ويُجمّد سعر الصرف
 */
interface TransitionEffectInterface
{
    /**
     * نفّذ التأثيرات الجانبية
     *
     * يُستدعى داخل DB::transaction، فلا تبدأ transaction جديدة
     *
     * @param  array<string, mixed>  $context
     */
    public function execute(PurchaseOrder $order, array $context): void;
}
