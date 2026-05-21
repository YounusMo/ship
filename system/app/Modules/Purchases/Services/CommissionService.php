<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Enums\CommissionType;
use App\Modules\Purchases\Exceptions\PurchaseException;
use App\Modules\Purchases\Support\MoneyHelper;

/**
 * حساب العمولات
 *
 * 3 أنواع:
 * - NONE: بدون عمولة (مع notes إجباري)
 * - PERCENTAGE: نسبة مئوية من قيمة الشراء
 * - FIXED_AMOUNT: مبلغ ثابت بالـ USD
 *
 * @see CLAUDE.md Section 4 - مرونة العمولة
 */
class CommissionService
{
    /**
     * حساب مبلغ العمولة
     *
     * @return array{commission_amount: string, total_amount: string}
     */
    public function calculate(
        string $baseAmount,
        CommissionType $type,
        ?string $value = null,
        ?string $notes = null,
    ): array {
        $this->validate($type, $value, $notes);

        $commissionAmount = match ($type) {
            CommissionType::NONE => '0.00',
            CommissionType::PERCENTAGE => MoneyHelper::percentage($baseAmount, $value ?? '0'),
            CommissionType::FIXED_AMOUNT => $value ?? '0.00',
        };

        return [
            'commission_amount' => $commissionAmount,
            'total_amount' => MoneyHelper::add($baseAmount, $commissionAmount),
        ];
    }

    /**
     * التحقق من صحة إعدادات العمولة
     */
    public function validate(CommissionType $type, ?string $value, ?string $notes): void
    {
        // لو NONE، لازم notes
        if ($type === CommissionType::NONE) {
            if ($notes === null || strlen(trim($notes)) < 10) {
                throw new PurchaseException(
                    message: 'Commission notes required when type is NONE (min 10 chars)',
                    messageAr: 'ملاحظات العمولة إجبارية عند اختيار "بدون عمولة" (10 أحرف على الأقل)',
                    errorCode: 'MISSING_COMMISSION_NOTES',
                );
            }
            return;
        }

        // لو NOT NONE، لازم value
        if ($value === null || MoneyHelper::lte($value, '0')) {
            throw new PurchaseException(
                message: 'Commission value must be positive',
                messageAr: 'قيمة العمولة يجب أن تكون أكبر من صفر',
                errorCode: 'INVALID_COMMISSION_VALUE',
            );
        }

        // PERCENTAGE: لازم بين 0 و 100
        if ($type === CommissionType::PERCENTAGE) {
            if (MoneyHelper::gt($value, '100')) {
                throw new PurchaseException(
                    message: 'Commission percentage cannot exceed 100%',
                    messageAr: 'نسبة العمولة لا يمكن أن تتجاوز 100%',
                    errorCode: 'INVALID_COMMISSION_VALUE',
                );
            }
        }
    }
}
