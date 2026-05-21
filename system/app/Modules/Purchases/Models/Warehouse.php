<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $name_en
 * @property string $country
 * @property string $city
 * @property string|null $address
 * @property string $local_currency
 * @property string|null $phone
 * @property string|null $manager_name
 * @property bool $is_active
 */
class Warehouse extends Model
{
    use HasFactory;

    protected $table = 'warehouses';

    protected $fillable = [
        'code',
        'name',
        'name_en',
        'country',
        'city',
        'address',
        'local_currency',
        'phone',
        'manager_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function buyers(): HasMany
    {
        return $this->hasMany(Buyer::class, 'primary_warehouse_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
