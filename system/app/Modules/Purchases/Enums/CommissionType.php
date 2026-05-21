<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum CommissionType: string
{
    case NONE = 'NONE';
    case PERCENTAGE = 'PERCENTAGE';
    case FIXED_AMOUNT = 'FIXED_AMOUNT';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'بدون عمولة',
            self::PERCENTAGE => 'نسبة مئوية',
            self::FIXED_AMOUNT => 'مبلغ ثابت',
        };
    }
}
