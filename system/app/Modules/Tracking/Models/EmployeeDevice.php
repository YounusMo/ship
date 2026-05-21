<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDevice extends Model
{
    protected $table = 'employee_devices';

    protected $fillable = [
        'user_id', 'platform', 'token',
        'app_version', 'device_model', 'os_version',
        'last_seen_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('revoked_at');
    }
}
