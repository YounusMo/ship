<?php

declare(strict_types=1);

namespace App\Modules\Purchases\StateMachine;

use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Models\PurchaseOrder;

/**
 * يمثل انتقال واحد في الـ State Machine
 */
final class Transition
{
    public function __construct(
        public readonly PurchaseOrderStatus $from,
        public readonly PurchaseOrderStatus $to,
        public readonly ?string $guardClass = null,
        public readonly ?string $effectClass = null,
    ) {
    }

    /**
     * تنفيذ الـ guard (التحقق من الشروط)
     *
     * @param  array<string, mixed>  $context
     */
    public function checkGuard(PurchaseOrder $order, array $context): void
    {
        if ($this->guardClass === null) {
            return;
        }

        $guard = app($this->guardClass);

        if (!$guard instanceof TransitionGuardInterface) {
            throw new \LogicException(
                "Guard {$this->guardClass} must implement TransitionGuardInterface"
            );
        }

        $guard->check($order, $context);
    }

    /**
     * تنفيذ الـ effect (التأثيرات الجانبية)
     *
     * @param  array<string, mixed>  $context
     */
    public function executeEffect(PurchaseOrder $order, array $context): void
    {
        if ($this->effectClass === null) {
            return;
        }

        $effect = app($this->effectClass);

        if (!$effect instanceof TransitionEffectInterface) {
            throw new \LogicException(
                "Effect {$this->effectClass} must implement TransitionEffectInterface"
            );
        }

        $effect->execute($order, $context);
    }
}
