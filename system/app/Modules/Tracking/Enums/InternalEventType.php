<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Enums;

/**
 * Event types emitted by employee scans inside Libya.
 * INTERNATIONAL events use free-form string codes coming from ShipsGo;
 * INTERNAL events are constrained to this enum because they're authored
 * by us and the state machine validates against the same list.
 */
enum InternalEventType: string
{
    case RECEIVED_AT_HUB        = 'RECEIVED_AT_HUB';
    case IN_TRANSIT_INTERNAL    = 'IN_TRANSIT_INTERNAL';
    case RECEIVED_AT_BRANCH     = 'RECEIVED_AT_BRANCH';
    case READY_FOR_PICKUP       = 'READY_FOR_PICKUP';
    case DELIVERED_TO_CUSTOMER  = 'DELIVERED_TO_CUSTOMER';
    case RETURNED_TO_HUB        = 'RETURNED_TO_HUB';
    case LOST                   = 'LOST';
    case DAMAGED                = 'DAMAGED';
}
