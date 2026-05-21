<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Enums\BuyerStatus;
use App\Modules\Purchases\Enums\BuyerTransactionType;
use App\Modules\Purchases\Events\BuyerLowBalanceDetected;
use App\Modules\Purchases\Exceptions\BuyerNotActiveException;
use App\Modules\Purchases\Exceptions\InsufficientBalanceException;
use App\Modules\Purchases\Exceptions\PurchaseException;
use App\Modules\Purchases\Models\Buyer;
use App\Modules\Purchases\Models\BuyerAccount;
use App\Modules\Purchases\Models\BuyerTransaction;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Support\MoneyHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * إدارة حسابات العُهدة لمسؤولي المشتريات
 *
 * 🚨 كل العمليات هنا تتعامل مع المال - كلها داخل transaction
 *
 * @see CLAUDE.md Section 4 - نظام العُهدة
 * @see CLAUDE.md Section 6 - القيود المحاسبية
 */
class BuyerAccountService
{
    public function __construct(
        private readonly AccountingIntegrationService $accounting,
        private readonly AuditLogService $audit,
    ) {
    }

    /**
     * الوديعة الأولية لمسؤول جديد
     */
    public function initialDeposit(
        Buyer $buyer,
        string $amount,
        ?int $performedById = null,
        ?string $notes = null,
    ): BuyerTransaction {
        $this->assertBuyerActive($buyer);
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($buyer, $amount, $performedById, $notes) {
            $account = $this->lockAccount($buyer);

            // تحقق: ما فيه إيداع أولي سابق
            $hasInitialDeposit = BuyerTransaction::query()
                ->where('buyer_id', $buyer->id)
                ->where('type', BuyerTransactionType::INITIAL_DEPOSIT)
                ->exists();

            if ($hasInitialDeposit) {
                throw new PurchaseException(
                    message: 'Initial deposit already exists. Use TOP_UP instead.',
                    messageAr: 'الوديعة الأولية موجودة بالفعل. استخدم إعادة التغذية.',
                );
            }

            $balanceBefore = (string) $account->balance;
            $balanceAfter = MoneyHelper::add($balanceBefore, $amount);

            $transaction = $this->createTransaction(
                account: $account,
                type: BuyerTransactionType::INITIAL_DEPOSIT,
                amount: $amount,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                description: 'الوديعة الأولية',
                performedById: $performedById,
                notes: $notes,
            );

            $account->update([
                'balance' => $balanceAfter,
                'total_received' => MoneyHelper::add((string) $account->total_received, $amount),
            ]);

            // قيد محاسبي
            $this->accounting->postInitialDeposit($buyer, $amount, $transaction);

            $this->audit->log(
                entityType: 'BuyerAccount',
                entityId: (string) $account->id,
                action: 'INITIAL_DEPOSIT',
                newValues: ['amount' => $amount, 'balance_after' => $balanceAfter],
            );

            return $transaction;
        });
    }

    /**
     * إعادة تغذية الوديعة
     */
    public function topUp(
        Buyer $buyer,
        string $amount,
        ?int $performedById = null,
        ?string $notes = null,
    ): BuyerTransaction {
        $this->assertBuyerActive($buyer);
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($buyer, $amount, $performedById, $notes) {
            $account = $this->lockAccount($buyer);

            $balanceBefore = (string) $account->balance;
            $balanceAfter = MoneyHelper::add($balanceBefore, $amount);

            $transaction = $this->createTransaction(
                account: $account,
                type: BuyerTransactionType::TOP_UP,
                amount: $amount,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                description: 'إعادة تغذية الوديعة',
                performedById: $performedById,
                notes: $notes,
            );

            $account->update([
                'balance' => $balanceAfter,
                'total_received' => MoneyHelper::add((string) $account->total_received, $amount),
            ]);

            $this->accounting->postTopUp($buyer, $amount, $transaction);

            $this->audit->log(
                entityType: 'BuyerAccount',
                entityId: (string) $account->id,
                action: 'TOP_UP',
                newValues: ['amount' => $amount, 'balance_after' => $balanceAfter],
            );

            return $transaction;
        });
    }

    /**
     * خصم من العُهدة لشراء منتج
     *
     * ⚠️ هذه أهم method في الخدمة
     */
    public function chargeBuyerForPurchase(
        Buyer $buyer,
        PurchaseOrder $order,
        string $localAmount,
        string $localCurrency,
        string $exchangeRate,
        ?string $invoiceImageUrl = null,
        ?string $notes = null,
    ): BuyerTransaction {
        $this->assertBuyerActive($buyer);

        // حسب USD = localAmount / exchangeRate (مثلاً 700 CNY / 7.20 = 97.22 USD)
        $usdAmount = MoneyHelper::div($localAmount, $exchangeRate);

        return DB::transaction(function () use (
            $buyer, $order, $localAmount, $localCurrency,
            $exchangeRate, $invoiceImageUrl, $notes, $usdAmount,
        ) {
            $account = $this->lockAccount($buyer);

            // تحقق من كفاية الرصيد
            $availableBalance = $account->availableBalance();
            if (MoneyHelper::lt($availableBalance, $usdAmount)) {
                throw InsufficientBalanceException::forBuyer(
                    $buyer->id,
                    $usdAmount,
                    $availableBalance,
                );
            }

            // تحقق من max_order_value
            if (MoneyHelper::gt($usdAmount, (string) $buyer->max_order_value)) {
                throw new PurchaseException(
                    message: "Order value {$usdAmount} USD exceeds buyer max {$buyer->max_order_value} USD",
                    messageAr: "قيمة الشراء {$usdAmount} USD تتجاوز حد المسؤول {$buyer->max_order_value} USD",
                    errorCode: 'MAX_ORDER_VALUE_EXCEEDED',
                );
            }

            $balanceBefore = (string) $account->balance;
            $balanceAfter = MoneyHelper::sub($balanceBefore, $usdAmount);

            // المعاملة (المبلغ سالب لأنه خصم)
            $transaction = BuyerTransaction::create([
                'buyer_account_id' => $account->id,
                'buyer_id' => $buyer->id,
                'type' => BuyerTransactionType::PURCHASE,
                'amount' => MoneyHelper::negate($usdAmount),
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'local_amount' => $localAmount,
                'local_currency' => $localCurrency,
                'exchange_rate' => $exchangeRate,
                'purchase_order_id' => $order->id,
                'invoice_image_url' => $invoiceImageUrl,
                'description' => "شراء للطلب {$order->order_number}",
                'notes' => $notes,
                'performed_by_id' => Auth::id() ?? $buyer->user_id,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'transaction_date' => now(),
            ]);

            // حدّث الحساب
            $account->update([
                'balance' => $balanceAfter,
                'total_spent' => MoneyHelper::add((string) $account->total_spent, $usdAmount),
            ]);

            // قيد محاسبي:
            //   مدين: مشتريات تحت التسليم
            //   دائن: عُهدة المشتريات
            $this->accounting->postPurchaseExecution($buyer, $order, $usdAmount, $transaction);

            // إشعار لو الرصيد قارب على النفاد
            $newAvailable = MoneyHelper::sub($balanceAfter, (string) $account->reserved_balance);
            if (MoneyHelper::lt($newAvailable, (string) $account->min_threshold)) {
                event(new BuyerLowBalanceDetected($account->fresh(), (string) $account->min_threshold));
            }

            $this->audit->log(
                entityType: 'BuyerAccount',
                entityId: (string) $account->id,
                action: 'PURCHASE_CHARGE',
                newValues: [
                    'order_id' => $order->id,
                    'local_amount' => $localAmount,
                    'local_currency' => $localCurrency,
                    'exchange_rate' => $exchangeRate,
                    'usd_amount' => $usdAmount,
                    'balance_after' => $balanceAfter,
                ],
            );

            return $transaction;
        });
    }

    /**
     * إرجاع للعُهدة (لو الطلب اتلغى بعد الشراء)
     */
    public function refundBuyer(
        Buyer $buyer,
        PurchaseOrder $order,
        string $usdAmount,
        string $reason,
    ): BuyerTransaction {
        $this->assertPositiveAmount($usdAmount);

        return DB::transaction(function () use ($buyer, $order, $usdAmount, $reason) {
            $account = $this->lockAccount($buyer);

            $balanceBefore = (string) $account->balance;
            $balanceAfter = MoneyHelper::add($balanceBefore, $usdAmount);

            $transaction = BuyerTransaction::create([
                'buyer_account_id' => $account->id,
                'buyer_id' => $buyer->id,
                'type' => BuyerTransactionType::PURCHASE_REFUND,
                'amount' => $usdAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'purchase_order_id' => $order->id,
                'description' => "إرجاع للطلب {$order->order_number}",
                'notes' => $reason,
                'performed_by_id' => Auth::id() ?? $buyer->user_id,
                'transaction_date' => now(),
            ]);

            $account->update([
                'balance' => $balanceAfter,
                'total_spent' => MoneyHelper::sub((string) $account->total_spent, $usdAmount),
            ]);

            // قيد عكسي للشراء
            $this->accounting->postPurchaseRefund($buyer, $order, $usdAmount, $transaction);

            return $transaction;
        });
    }

    /**
     * تحويل بين مسؤولين
     */
    public function transferBetweenBuyers(
        Buyer $fromBuyer,
        Buyer $toBuyer,
        string $amount,
        string $reason,
    ): array {
        $this->assertBuyerActive($fromBuyer);
        $this->assertBuyerActive($toBuyer);
        $this->assertPositiveAmount($amount);

        if ($fromBuyer->id === $toBuyer->id) {
            throw new PurchaseException(
                message: 'Cannot transfer to the same buyer',
                messageAr: 'لا يمكن التحويل لنفس المسؤول',
            );
        }

        return DB::transaction(function () use ($fromBuyer, $toBuyer, $amount, $reason) {
            // Lock both accounts (always in same order to avoid deadlock)
            $accounts = [$fromBuyer->id, $toBuyer->id];
            sort($accounts);
            foreach ($accounts as $bid) {
                BuyerAccount::query()->where('buyer_id', $bid)->lockForUpdate()->first();
            }

            $fromAccount = BuyerAccount::query()->where('buyer_id', $fromBuyer->id)->first();
            $toAccount = BuyerAccount::query()->where('buyer_id', $toBuyer->id)->first();

            $available = $fromAccount->availableBalance();
            if (MoneyHelper::lt($available, $amount)) {
                throw InsufficientBalanceException::forBuyer($fromBuyer->id, $amount, $available);
            }

            // OUT من المُحوِّل
            $outTx = BuyerTransaction::create([
                'buyer_account_id' => $fromAccount->id,
                'buyer_id' => $fromBuyer->id,
                'type' => BuyerTransactionType::TRANSFER_OUT,
                'amount' => MoneyHelper::negate($amount),
                'balance_before' => $fromAccount->balance,
                'balance_after' => MoneyHelper::sub((string) $fromAccount->balance, $amount),
                'description' => "تحويل إلى {$toBuyer->full_name}",
                'notes' => $reason,
                'performed_by_id' => Auth::id(),
                'transaction_date' => now(),
            ]);

            $fromAccount->decrement('balance', (float) $amount);

            // IN للمُحوَّل له
            $inTx = BuyerTransaction::create([
                'buyer_account_id' => $toAccount->id,
                'buyer_id' => $toBuyer->id,
                'type' => BuyerTransactionType::TRANSFER_IN,
                'amount' => $amount,
                'balance_before' => $toAccount->balance,
                'balance_after' => MoneyHelper::add((string) $toAccount->balance, $amount),
                'description' => "تحويل من {$fromBuyer->full_name}",
                'notes' => $reason,
                'performed_by_id' => Auth::id(),
                'transaction_date' => now(),
            ]);

            $toAccount->increment('balance', (float) $amount);

            return ['out' => $outTx, 'in' => $inTx];
        });
    }

    // ─── Helper Methods ──────────────────────────────────────────

    /**
     * Lock الحساب لمنع race conditions
     */
    private function lockAccount(Buyer $buyer): BuyerAccount
    {
        $account = BuyerAccount::query()
            ->where('buyer_id', $buyer->id)
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            throw new PurchaseException(
                message: "Buyer {$buyer->id} has no account",
                messageAr: 'المسؤول لا يملك حساب عُهدة',
            );
        }

        return $account;
    }

    private function assertBuyerActive(Buyer $buyer): void
    {
        if ($buyer->status !== BuyerStatus::ACTIVE) {
            throw new BuyerNotActiveException(
                message: "Buyer {$buyer->id} is not active (status: {$buyer->status->value})",
                messageAr: "مسؤول المشتريات غير نشط (الحالة: {$buyer->status->value})",
            );
        }
    }

    private function assertPositiveAmount(string $amount): void
    {
        if (MoneyHelper::lte($amount, '0')) {
            throw new PurchaseException(
                message: 'Amount must be positive',
                messageAr: 'المبلغ يجب أن يكون أكبر من صفر',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function createTransaction(
        BuyerAccount $account,
        BuyerTransactionType $type,
        string $amount,
        string $balanceBefore,
        string $balanceAfter,
        string $description,
        ?int $performedById = null,
        ?string $notes = null,
        array $extra = [],
    ): BuyerTransaction {
        return BuyerTransaction::create(array_merge([
            'buyer_account_id' => $account->id,
            'buyer_id' => $account->buyer_id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'notes' => $notes,
            'performed_by_id' => $performedById ?? Auth::id(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'transaction_date' => now(),
        ], $extra));
    }
}

// نضيف method ناقص في MoneyHelper:
// public static function lte(...) - مضاف للـ helper
