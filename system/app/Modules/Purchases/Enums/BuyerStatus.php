<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum BuyerStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case TERMINATED = 'TERMINATED';
}
