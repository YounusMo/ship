<?php

declare(strict_types=1);

// Customer-facing event labels. Resolved by UnifiedTimelineService via
// trans($event->translation_key, $event->translation_params, $locale).
// The :city placeholder is filled from translation_params when set.
//
// Keys mirror the producer codes:
//   - INTERNATIONAL events use ShipsGo's uppercase event codes.
//   - INTERNAL events use App\Modules\Tracking\Enums\InternalEventType /
//     CustodyEventType case names.
//
// Add new keys when ShipsGo introduces an event we want to surface. The
// fallback when no key matches is the raw event_type code — visible but
// ugly, so prefer to add a translation here.

return [

    /*
    |--------------------------------------------------------------------------
    | International (ShipsGo)
    |--------------------------------------------------------------------------
    */

    'BOOKING_CONFIRMED'   => 'Booking confirmed',
    'EMPTY_TO_SHIPPER'    => 'Empty container released to shipper',
    'GATE_IN'             => 'Container arrived at origin port :city',
    'GATE_IN_FULL'        => 'Container received at origin port :city',
    'LOADED'              => 'Container loaded onto vessel in :city',
    'LOADED_ON_VESSEL'    => 'Container loaded onto vessel in :city',
    'DEPARTED'            => 'Vessel departed :city',
    'VESSEL_DEPARTURE'    => 'Vessel departed :city',
    'IN_TRANSIT'          => 'In transit',
    'TRANSHIPMENT'        => 'Transhipment at :city',
    'ARRIVED'             => 'Vessel arrived at :city',
    'VESSEL_ARRIVAL'      => 'Vessel arrived at :city',
    'DISCHARGED'          => 'Container discharged at :city',
    'DISCHARGED_FROM_VESSEL' => 'Container discharged at :city',
    'GATE_OUT'            => 'Container left port :city',
    'GATE_OUT_FULL'       => 'Container left port :city',
    'EMPTY_RETURN'        => 'Empty container returned',
    'CUSTOMS_HOLD'        => 'Held by customs at :city',
    'CUSTOMS_RELEASED'    => 'Cleared by customs at :city',
    'DELIVERED'           => 'Delivered',
    'SHIPMENT_DELETED'    => 'Shipment removed',

    /*
    |--------------------------------------------------------------------------
    | Internal custody (warehouse + last-mile)
    |--------------------------------------------------------------------------
    */

    'RECEIVED_AT_HUB'       => 'Received at hub',
    'IN_TRANSIT_INTERNAL'   => 'In transit to branch',
    'DISPATCHED'            => 'Dispatched',
    'RECEIVED_AT_BRANCH'    => 'Received at branch',
    'READY_FOR_PICKUP'      => 'Ready for pickup',
    'DELIVERED_TO_CUSTOMER' => 'Delivered to customer',
    'RETURNED_TO_HUB'       => 'Returned to hub',
    'LOST'                  => 'Reported lost',
    'DAMAGED'               => 'Reported damaged',
];
