<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Exceptions;

use App\Modules\Purchases\Enums\PurchaseOrderStatus;

class InvalidTransitionException extends PurchaseException
{
    protected string $errorCode = 'INVALID_TRANSITION';

    public static function notAllowed(
        PurchaseOrderStatus $from,
        PurchaseOrderStatus $to,
        ?int $orderId = null,
    ): self {
        return new self(
            message: "Cannot transition from {$from->value} to {$to->value}",
            messageAr: "لا يمكن الانتقال من {$from->label()} إلى {$to->label()}",
            context: [
                'from' => $from->value,
                'to' => $to->value,
                'order_id' => $orderId,
            ],
        );
    }

    public static function guardFailed(
        PurchaseOrderStatus $from,
        PurchaseOrderStatus $to,
        string $reason,
    ): self {
        return new self(
            message: "Transition {$from->value} → {$to->value} failed: {$reason}",
            messageAr: "فشل الانتقال {$from->label()} → {$to->label()}: {$reason}",
            context: [
                'from' => $from->value,
                'to' => $to->value,
                'reason' => $reason,
            ],
            errorCode: 'TRANSITION_GUARD_FAILED',
        );
    }
}
