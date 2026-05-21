<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum RateStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUPERSEDED = 'SUPERSEDED';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case REJECTED = 'REJECTED';
}
