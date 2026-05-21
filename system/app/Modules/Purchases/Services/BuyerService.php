<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Enums\BuyerStatus;
use App\Modules\Purchases\Exceptions\PurchaseException;
use App\Modules\Purchases\Models\Buyer;
use App\Modules\Purchases\Models\BuyerAccount;
use App\Modules\Purchases\Support\MoneyHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BuyerService
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {
    }

    /**
     * إنشاء مسؤول مشتريات جديد + حساب عُهدته
     *
     * @param  array{
     *     user_id: int,
     *     full_name: string,
     *     phone: string,
     *     primary_warehouse_id: int,
     *     max_order_value?: string,
     *     can_approve_orders?: bool,
     * }  $input
     */
    public function createBuyer(array $input): Buyer
    {
        return DB::transaction(function () use ($input) {
            $buyer = Buyer::create([
                'user_id' => $input['user_id'],
                'code' => $this->generateBuyerCode(),
                'full_name' => $input['full_name'],
                'phone' => $input['phone'],
                'primary_warehouse_id' => $input['primary_warehouse_id'],
                'max_order_value' => $input['max_order_value']
                    ?? config('purchases.business.default_buyer_max_order_value_usd'),
                'can_approve_orders' => $input['can_approve_orders'] ?? false,
                'status' => BuyerStatus::ACTIVE,
                'hired_at' => now(),
            ]);

            BuyerAccount::create([
                'buyer_id' => $buyer->id,
                'balance' => 0,
                'reserved_balance' => 0,
                'total_spent' => 0,
                'total_received' => 0,
                'min_threshold' => config('purchases.business.default_buyer_min_balance_threshold_usd'),
                'is_active' => true,
            ]);

            $this->audit->log(
                entityType: 'Buyer',
                entityId: (string) $buyer->id,
                action: 'CREATE',
                newValues: $buyer->toArray(),
            );

            return $buyer->fresh('account');
        });
    }

    public function suspend(Buyer $buyer, string $reason): Buyer
    {
        $buyer->update([
            'status' => BuyerStatus::SUSPENDED,
        ]);

        $this->audit->log(
            entityType: 'Buyer',
            entityId: (string) $buyer->id,
            action: 'SUSPEND',
            reason: $reason,
        );

        return $buyer->fresh();
    }

    /**
     * إنهاء خدمة مسؤول
     *
     * ⚠️ يتطلب تصفير رصيد العُهدة أولاً
     */
    public function terminate(Buyer $buyer, string $reason): Buyer
    {
        return DB::transaction(function () use ($buyer, $reason) {
            $account = $buyer->account;

            if ($account === null) {
                throw new PurchaseException(
                    message: 'Buyer has no account',
                    messageAr: 'المسؤول لا يملك حساب',
                );
            }

            // تحقق من تصفير الرصيد
            if (!MoneyHelper::eq((string) $account->balance, '0')) {
                throw new PurchaseException(
                    message: "Cannot terminate buyer with balance {$account->balance} USD",
                    messageAr: "لا يمكن إنهاء الخدمة قبل تصفير العُهدة (الرصيد الحالي: {$account->balance} USD)",
                    errorCode: 'BUYER_HAS_ACTIVE_BALANCE',
                );
            }

            // تحقق من عدم وجود رصيد محجوز
            if (!MoneyHelper::eq((string) $account->reserved_balance, '0')) {
                throw new PurchaseException(
                    message: "Buyer has reserved balance {$account->reserved_balance} USD",
                    messageAr: "هناك رصيد محجوز على طلبات نشطة",
                    errorCode: 'BUYER_HAS_RESERVED_BALANCE',
                );
            }

            $buyer->update([
                'status' => BuyerStatus::TERMINATED,
                'terminated_at' => now(),
            ]);

            $account->update(['is_active' => false]);

            $this->audit->log(
                entityType: 'Buyer',
                entityId: (string) $buyer->id,
                action: 'TERMINATE',
                reason: $reason,
            );

            return $buyer->fresh();
        });
    }

    public function reactivate(Buyer $buyer): Buyer
    {
        if ($buyer->status === BuyerStatus::TERMINATED) {
            throw new PurchaseException(
                message: 'Cannot reactivate terminated buyer',
                messageAr: 'لا يمكن إعادة تفعيل مسؤول منتهي الخدمة',
            );
        }

        $buyer->update(['status' => BuyerStatus::ACTIVE]);

        $this->audit->log(
            entityType: 'Buyer',
            entityId: (string) $buyer->id,
            action: 'REACTIVATE',
        );

        return $buyer->fresh();
    }

    private function generateBuyerCode(): string
    {
        $lastBuyer = Buyer::query()
            ->where('code', 'like', 'BUYER-%')
            ->orderByDesc('code')
            ->first();

        $sequence = 1;
        if ($lastBuyer !== null) {
            $lastSeq = (int) substr($lastBuyer->code, 6);
            $sequence = $lastSeq + 1;
        }

        return sprintf('BUYER-%03d', $sequence);
    }
}
