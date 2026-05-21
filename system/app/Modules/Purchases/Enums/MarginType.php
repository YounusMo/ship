<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum MarginType: string
{
    case NONE = 'NONE';
    case PERCENTAGE = 'PERCENTAGE';
    case FIXED_AMOUNT = 'FIXED_AMOUNT';
}
