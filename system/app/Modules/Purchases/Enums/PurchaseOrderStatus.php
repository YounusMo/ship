<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

/**
 * حالات طلب الشراء
 *
 * @see CLAUDE.md Section 3 - State Machine
 */
enum PurchaseOrderStatus: string
{
    case PENDING_CONFIRMATION = 'PENDING_CONFIRMATION';
    case CONFIRMED = 'CONFIRMED';
    case PURCHASING = 'PURCHASING';
    case PURCHASED = 'PURCHASED';
    case RECEIVED_WAREHOUSE = 'RECEIVED_WAREHOUSE';
    case IN_SHIPMENT = 'IN_SHIPMENT';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';
    case RETURNED = 'RETURNED';
    case REFUNDED = 'REFUNDED';
    case ON_HOLD = 'ON_HOLD';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_CONFIRMATION => 'ينتظر التأكيد',
            self::CONFIRMED => 'مؤكد',
            self::PURCHASING => 'قيد الشراء',
            self::PURCHASED => 'تم الشراء',
            self::RECEIVED_WAREHOUSE => 'في المستودع',
            self::IN_SHIPMENT => 'قيد الشحن',
            self::DELIVERED => 'تم التسليم',
            self::CANCELLED => 'ملغي',
            self::RETURNED => 'مرتجع',
            self::REFUNDED => 'تم الاسترداد',
            self::ON_HOLD => 'معلّق',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::PENDING_CONFIRMATION => 'Pending Confirmation',
            self::CONFIRMED => 'Confirmed',
            self::PURCHASING => 'Purchasing',
            self::PURCHASED => 'Purchased',
            self::RECEIVED_WAREHOUSE => 'Received at Warehouse',
            self::IN_SHIPMENT => 'In Shipment',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            self::RETURNED => 'Returned',
            self::REFUNDED => 'Refunded',
            self::ON_HOLD => 'On Hold',
        };
    }

    /**
     * هل الحالة نهائية (لا يمكن الانتقال منها)؟
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::DELIVERED,
            self::REFUNDED,
        ], true);
    }

    /**
     * هل الطلب قابل للإلغاء من هذه الحالة؟
     */
    public function isCancellable(): bool
    {
        return in_array($this, [
            self::PENDING_CONFIRMATION,
            self::CONFIRMED,
            self::PURCHASING,
            self::PURCHASED,
            self::ON_HOLD,
        ], true);
    }

    /**
     * هل الطلب جاهز للإضافة لرحلة؟
     */
    public function isReadyForShipment(): bool
    {
        return $this === self::RECEIVED_WAREHOUSE;
    }
}
