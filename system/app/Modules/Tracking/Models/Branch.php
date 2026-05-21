<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Modules\Tracking\Enums\BranchRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $name_en
 * @property BranchRole $role
 * @property string $country
 * @property string $city
 * @property string|null $address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $phone
 * @property bool $is_active
 */
class Branch extends Model
{
    protected $table = 'tracking_branches';

    protected $fillable = [
        'code', 'name', 'name_en', 'role', 'country', 'city',
        'address', 'latitude', 'longitude', 'phone', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'role'      => BranchRole::class,
            'is_active' => 'boolean',
            'latitude'  => 'decimal:6',
            'longitude' => 'decimal:6',
        ];
    }

    public function staff(): HasMany
    {
        return $this->hasMany(BranchStaff::class, 'branch_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
