<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Enums;

enum TrackingEventKind: string
{
    case INTERNATIONAL = 'INTERNATIONAL';
    case INTERNAL = 'INTERNAL';
}
