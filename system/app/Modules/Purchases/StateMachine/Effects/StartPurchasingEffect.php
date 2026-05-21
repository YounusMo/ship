<?php

declare(strict_types=1);

namespace App\Modules\Purchases\StateMachine\Effects;

use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\StateMachine\TransitionEffectInterface;

/**
 * @todo: نفّذ منطق StartPurchasingEffect
 * @see CLAUDE.md Section 3 - State Machine
 * @see CLAUDE.md Section 6 - القيود المحاسبية
 */
class StartPurchasingEffect implements TransitionEffectInterface
{
    public function execute(PurchaseOrder $order, array $context): void
    {
        // TODO: implement effect logic
        // - Update order fields if needed
        // - Create journal entries
        // - Update buyer account
        // - Update wallet
        // etc.
    }
}
