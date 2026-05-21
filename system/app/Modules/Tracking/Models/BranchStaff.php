<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Models\User;
use App\Modules\Tracking\Enums\BranchStaffRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $user_id
 * @property BranchStaffRole $role
 * @property bool $is_active
 */
class BranchStaff extends Model
{
    protected $table = 'branch_staff';

    protected $fillable = ['branch_id', 'user_id', 'role', 'is_active'];

    protected function casts(): array
    {
        return [
            'role'      => BranchStaffRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
