<?php

declare(strict_types=1);

return [
    'status' => [
        'PENDING_CONFIRMATION' => 'Pending Confirmation',
        'CONFIRMED' => 'Confirmed',
        'PURCHASING' => 'Purchasing',
        'PURCHASED' => 'Purchased',
        'RECEIVED_WAREHOUSE' => 'Received at Warehouse',
        'IN_SHIPMENT' => 'In Shipment',
        'DELIVERED' => 'Delivered',
        'CANCELLED' => 'Cancelled',
        'RETURNED' => 'Returned',
        'REFUNDED' => 'Refunded',
        'ON_HOLD' => 'On Hold',
    ],

    'notifications' => [
        'confirmed' => 'Your order :order_number has been confirmed and the amount reserved from your wallet.',
        'purchasing' => 'We have started purchasing your items for order :order_number',
        'purchased' => 'Your items have been purchased. Order :order_number is awaiting arrival at warehouse.',
        'received' => 'Your items have arrived at the warehouse. Order :order_number is ready to ship.',
        'in_shipment' => 'Your items are shipped in trip :shipment_number. Order :order_number',
        'delivered' => 'Order :order_number delivered successfully. Thank you for choosing ShipFlow.',
        'cancelled' => 'Order :order_number has been cancelled. Reason: :reason',
    ],

    'errors' => [
        'insufficient_balance' => 'Insufficient balance',
        'invalid_transition' => 'Invalid transition',
        'rate_not_available' => 'No active exchange rate',
        'rate_spike' => 'Exchange rate spike requires approval',
        'missing_commission_notes' => 'Commission notes are required when type is NONE',
        'buyer_not_active' => 'Buyer is not active',
        'max_order_value_exceeded' => 'Order value exceeds buyer max',
    ],
];
