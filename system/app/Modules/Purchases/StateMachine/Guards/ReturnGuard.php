<?php

declare(strict_types=1);

namespace App\Modules\Purchases\StateMachine\Guards;

use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\StateMachine\TransitionGuardInterface;

/**
 * @todo: نفّذ منطق ReturnGuard
 * @see CLAUDE.md Section 3 - State Machine
 */
class ReturnGuard implements TransitionGuardInterface
{
    public function check(PurchaseOrder $order, array $context): void
    {
        // TODO: implement guard logic
    }
}
