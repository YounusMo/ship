<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Exceptions;

use App\Modules\Tracking\Enums\InternalEventType;
use RuntimeException;

class InvalidScanTransitionException extends RuntimeException
{
    public static function notAllowed(
        ?string $currentState,
        InternalEventType $proposed,
    ): self {
        $current = $currentState ?? 'NONE';
        return new self(
            "Cannot apply {$proposed->value} from current state {$current}"
        );
    }

    public static function branchScopeMismatch(int $branchId, int $expectedBranchId): self
    {
        return new self(
            "Scan recorded at branch {$branchId} but shipment is currently in branch {$expectedBranchId}"
        );
    }
}
