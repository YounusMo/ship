<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Enums;

enum CustodyEventType: string
{
    case RECEIVED_AT_HUB        = 'RECEIVED_AT_HUB';
    case DISPATCHED             = 'DISPATCHED';
    case RECEIVED_AT_BRANCH     = 'RECEIVED_AT_BRANCH';
    case READY_FOR_PICKUP       = 'READY_FOR_PICKUP';
    case DELIVERED_TO_CUSTOMER  = 'DELIVERED_TO_CUSTOMER';
    case RETURNED_TO_HUB        = 'RETURNED_TO_HUB';
    case LOST                   = 'LOST';
    case DAMAGED                = 'DAMAGED';
}
