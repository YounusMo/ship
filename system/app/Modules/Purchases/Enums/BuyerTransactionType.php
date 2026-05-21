<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum BuyerTransactionType: string
{
    case INITIAL_DEPOSIT = 'INITIAL_DEPOSIT';
    case TOP_UP = 'TOP_UP';
    case PURCHASE = 'PURCHASE';
    case PURCHASE_REFUND = 'PURCHASE_REFUND';
    case RECONCILIATION_ADJUSTMENT = 'RECONCILIATION_ADJUSTMENT';
    case TRANSFER_IN = 'TRANSFER_IN';
    case TRANSFER_OUT = 'TRANSFER_OUT';
    case WITHDRAWAL = 'WITHDRAWAL';

    /**
     * هل هذه المعاملة تزيد الرصيد؟
     */
    public function isCredit(): bool
    {
        return in_array($this, [
            self::INITIAL_DEPOSIT,
            self::TOP_UP,
            self::PURCHASE_REFUND,
            self::TRANSFER_IN,
        ], true);
    }

    /**
     * هل هذه المعاملة تنقص الرصيد؟
     */
    public function isDebit(): bool
    {
        return in_array($this, [
            self::PURCHASE,
            self::WITHDRAWAL,
            self::TRANSFER_OUT,
        ], true);
    }
}
