<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeActionLog extends Model
{
    protected $table = 'employee_action_logs';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'branch_id', 'action',
        'entity_type', 'entity_id', 'payload',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
