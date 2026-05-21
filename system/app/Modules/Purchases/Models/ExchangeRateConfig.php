<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Purchases\Enums\MarginType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeRateConfig extends Model
{
    use HasFactory;

    protected $table = 'exchange_rate_configs';

    protected $fillable = [
        'from_currency',
        'to_currency',
        'source',
        'primary_provider',
        'fallback_provider',
        'margin_type',
        'margin_value',
        'auto_update',
        'update_interval_hours',
        'max_deviation_pct',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'margin_type' => MarginType::class,
            'margin_value' => 'decimal:4',
            'auto_update' => 'boolean',
            'update_interval_hours' => 'integer',
            'max_deviation_pct' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'config_id');
    }

    public function activeRate()
    {
        return $this->hasOne(ExchangeRate::class, 'config_id')
            ->where('status', 'ACTIVE')
            ->latest('valid_from');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function pair(): string
    {
        return "{$this->from_currency}/{$this->to_currency}";
    }
}
