<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Models\User;
use App\Modules\Purchases\Enums\BuyerTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only model
 * لا يُحدّث ولا يُحذف منه أبداً
 */
class BuyerTransaction extends Model
{
    use HasFactory;

    protected $table = 'buyer_transactions';

    // ⚠️ الجدول Append-only - لا توجد updated_at
    public const UPDATED_AT = null;

    protected $fillable = [
        'buyer_account_id',
        'buyer_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'local_amount',
        'local_currency',
        'exchange_rate',
        'exchange_rate_id',
        'purchase_order_id',
        'invoice_image_url',
        'receipt_image_url',
        'proof_url',
        'description',
        'notes',
        'performed_by_id',
        'approved_by_id',
        'ip_address',
        'user_agent',
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'type' => BuyerTransactionType::class,
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'local_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'transaction_date' => 'datetime',
        ];
    }

    public function buyerAccount(): BelongsTo
    {
        return $this->belongsTo(BuyerAccount::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class);
    }

    /**
     * Override save لمنع التعديل بعد الإنشاء
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException(
                'BuyerTransaction is append-only. Use a reverse transaction instead.'
            );
        }
        return parent::save($options);
    }
}
