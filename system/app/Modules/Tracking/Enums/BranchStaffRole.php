<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Enums;

enum BranchStaffRole: string
{
    case MANAGER  = 'MANAGER';
    case RECEIVER = 'RECEIVER';
    case COURIER  = 'COURIER';
    case AUDITOR  = 'AUDITOR';
}
