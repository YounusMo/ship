<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Models\User;
use App\Modules\Purchases\Enums\BuyerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $user_id
 * @property string $code
 * @property string $full_name
 * @property string $phone
 * @property int $primary_warehouse_id
 * @property string $max_order_value
 * @property bool $can_approve_orders
 * @property BuyerStatus $status
 * @property \Carbon\Carbon $hired_at
 * @property \Carbon\Carbon|null $terminated_at
 */
class Buyer extends Model
{
    use HasFactory;

    protected $table = 'buyers';

    protected $fillable = [
        'user_id',
        'code',
        'full_name',
        'phone',
        'primary_warehouse_id',
        'max_order_value',
        'can_approve_orders',
        'status',
        'hired_at',
        'terminated_at',
    ];

    protected function casts(): array
    {
        return [
            'max_order_value' => 'decimal:2',
            'can_approve_orders' => 'boolean',
            'status' => BuyerStatus::class,
            'hired_at' => 'datetime',
            'terminated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'primary_warehouse_id');
    }

    public function account(): HasOne
    {
        return $this->hasOne(BuyerAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BuyerTransaction::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function isActive(): bool
    {
        return $this->status === BuyerStatus::ACTIVE;
    }

    public function scopeActive($query)
    {
        return $query->where('status', BuyerStatus::ACTIVE);
    }
}
