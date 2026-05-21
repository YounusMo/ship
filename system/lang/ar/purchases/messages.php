<?php

declare(strict_types=1);

return [
    // ─── حالات الطلب ────────────────────────────────────────
    'status' => [
        'PENDING_CONFIRMATION' => 'ينتظر التأكيد',
        'CONFIRMED' => 'مؤكد',
        'PURCHASING' => 'قيد الشراء',
        'PURCHASED' => 'تم الشراء',
        'RECEIVED_WAREHOUSE' => 'في المستودع',
        'IN_SHIPMENT' => 'قيد الشحن',
        'DELIVERED' => 'تم التسليم',
        'CANCELLED' => 'ملغي',
        'RETURNED' => 'مرتجع',
        'REFUNDED' => 'تم الاسترداد',
        'ON_HOLD' => 'معلّق',
    ],

    // ─── إشعارات الواتساب ────────────────────────────────────
    'notifications' => [
        'confirmed' => 'تم تأكيد طلبك :order_number وحجز المبلغ من محفظتك.',
        'purchasing' => 'بدأنا شراء منتجاتك للطلب :order_number',
        'purchased' => 'تم شراء منتجاتك بنجاح. الطلب :order_number بانتظار وصول البضاعة.',
        'received' => 'وصلت منتجاتك للمستودع. الطلب :order_number جاهز للشحن.',
        'in_shipment' => 'تم شحن منتجاتك في الرحلة :shipment_number. الطلب :order_number',
        'delivered' => 'تم تسليم طلبك :order_number بنجاح. شكراً لاختيارك ShipFlow.',
        'cancelled' => 'تم إلغاء طلبك :order_number. السبب: :reason',
    ],

    // ─── الأخطاء ─────────────────────────────────────────────
    'errors' => [
        'insufficient_balance' => 'الرصيد غير كافي',
        'invalid_transition' => 'انتقال غير مسموح',
        'rate_not_available' => 'لا يوجد سعر صرف نشط',
        'rate_spike' => 'تغير حاد في سعر الصرف يحتاج موافقة',
        'missing_commission_notes' => 'ملاحظات العمولة إجبارية عند اختيار "بدون عمولة"',
        'buyer_not_active' => 'مسؤول المشتريات غير نشط',
        'max_order_value_exceeded' => 'قيمة الطلب تتجاوز حد المسؤول',
    ],
];
