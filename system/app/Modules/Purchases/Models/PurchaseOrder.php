<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Models\Customer;
use App\Models\User;
use App\Modules\Purchases\Enums\CommissionType;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $order_number
 * @property int $customer_id
 * @property int $warehouse_id
 * @property int|null $buyer_id
 * @property string $purchase_currency
 * @property string $customer_currency
 * @property int|null $exchange_rate_id
 * @property string|null $frozen_exchange_rate
 * @property \Carbon\Carbon|null $exchange_rate_frozen_at
 * @property string|null $estimated_total_usd
 * @property string|null $actual_total_usd
 * @property CommissionType $commission_type
 * @property string|null $commission_value
 * @property string|null $commission_amount
 * @property PurchaseOrderStatus $status
 */
class PurchaseOrder extends Model
{
    use HasFactory;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'order_number',
        'customer_id',
        'warehouse_id',
        'buyer_id',
        'purchase_currency',
        'customer_currency',
        'exchange_rate_id',
        'frozen_exchange_rate',
        'exchange_rate_frozen_at',
        'estimated_purchase_amount',
        'estimated_total_usd',
        'actual_purchase_amount',
        'actual_total_usd',
        'commission_type',
        'commission_value',
        'commission_amount',
        'commission_notes',
        'customer_charged_amount',
        'customer_charged_usd',
        'status',
        'customer_notes',
        'internal_notes',
        'contact_source',
        'shipment_id',
        'container_id',
        'added_to_shipment_at',
        'tracking_number',
        'supplier_name',
        'supplier_contact',
        'requested_at',
        'confirmed_at',
        'purchasing_started_at',
        'purchased_at',
        'received_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by_id',
        'created_by_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'commission_type' => CommissionType::class,
            'frozen_exchange_rate' => 'decimal:8',
            'exchange_rate_frozen_at' => 'datetime',
            'estimated_purchase_amount' => 'decimal:2',
            'estimated_total_usd' => 'decimal:2',
            'actual_purchase_amount' => 'decimal:2',
            'actual_total_usd' => 'decimal:2',
            'commission_value' => 'decimal:4',
            'commission_amount' => 'decimal:2',
            'customer_charged_amount' => 'decimal:2',
            'customer_charged_usd' => 'decimal:2',
            'requested_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'purchasing_started_at' => 'datetime',
            'purchased_at' => 'datetime',
            'received_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'added_to_shipment_at' => 'datetime',
        ];
    }

    // ─── العلاقات ────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PurchaseOrderAttachment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PurchaseOrderStatusHistory::class)->orderBy('changed_at');
    }

    public function buyerTransactions(): HasMany
    {
        return $this->hasMany(BuyerTransaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeStatus($query, PurchaseOrderStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeReadyForShipment($query)
    {
        return $query->where('status', PurchaseOrderStatus::RECEIVED_WAREHOUSE)
            ->whereNull('shipment_id');
    }

    public function scopeForBuyer($query, int $buyerId)
    {
        return $query->where('buyer_id', $buyerId);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    // ─── Helper Methods ──────────────────────────────────────────

    public function isCancellable(): bool
    {
        return $this->status->isCancellable();
    }

    public function isReadyForShipment(): bool
    {
        return $this->status->isReadyForShipment() && $this->shipment_id === null;
    }

    public function hasFrozenRate(): bool
    {
        return $this->frozen_exchange_rate !== null;
    }
}
