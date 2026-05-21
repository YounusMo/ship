<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Models\User;
use App\Modules\Purchases\Enums\MarginType;
use App\Modules\Purchases\Enums\RateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $table = 'exchange_rates';

    protected $fillable = [
        'config_id',
        'from_currency',
        'to_currency',
        'raw_rate',
        'raw_source',
        'raw_fetched_at',
        'margin_type',
        'margin_value',
        'margin_amount',
        'effective_rate',
        'is_manual_override',
        'override_by_id',
        'override_reason',
        'status',
        'valid_from',
        'valid_until',
        'requires_approval',
        'approved_by_id',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'raw_rate' => 'decimal:8',
            'raw_fetched_at' => 'datetime',
            'margin_type' => MarginType::class,
            'margin_value' => 'decimal:4',
            'margin_amount' => 'decimal:8',
            'effective_rate' => 'decimal:8',
            'is_manual_override' => 'boolean',
            'status' => RateStatus::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'requires_approval' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(ExchangeRateConfig::class, 'config_id');
    }

    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function ordersUsingRate(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'exchange_rate_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', RateStatus::ACTIVE);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', RateStatus::PENDING_APPROVAL);
    }

    public function pair(): string
    {
        return "{$this->from_currency}/{$this->to_currency}";
    }
}
