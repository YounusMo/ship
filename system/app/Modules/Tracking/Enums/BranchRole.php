<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Enums;

enum BranchRole: string
{
    case HUB   = 'HUB';
    case SPOKE = 'SPOKE';
    case ADMIN = 'ADMIN';
}
