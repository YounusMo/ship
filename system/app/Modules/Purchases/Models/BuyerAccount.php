<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $buyer_id
 * @property string $balance               الرصيد المتاح (USD)
 * @property string $reserved_balance      المحجوز (USD)
 * @property string $total_spent
 * @property string $total_received
 * @property string $min_threshold
 * @property \Carbon\Carbon|null $last_reconciled_at
 * @property bool $is_active
 */
class BuyerAccount extends Model
{
    use HasFactory;

    protected $table = 'buyer_accounts';

    protected $fillable = [
        'buyer_id',
        'balance',
        'reserved_balance',
        'total_spent',
        'total_received',
        'min_threshold',
        'last_reconciled_at',
        'last_reconciled_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'reserved_balance' => 'decimal:2',
            'total_spent' => 'decimal:2',
            'total_received' => 'decimal:2',
            'min_threshold' => 'decimal:2',
            'last_reconciled_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BuyerTransaction::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BuyerReconciliation::class);
    }

    /**
     * الرصيد المتاح فعلياً للاستخدام (balance - reserved)
     */
    public function availableBalance(): string
    {
        return bcsub((string) $this->balance, (string) $this->reserved_balance, 2);
    }

    public function isLowBalance(): bool
    {
        return bccomp($this->availableBalance(), (string) $this->min_threshold, 2) < 0;
    }
}
