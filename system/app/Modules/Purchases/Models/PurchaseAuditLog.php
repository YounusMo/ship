<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit log
 */
class PurchaseAuditLog extends Model
{
    use HasFactory;

    protected $table = 'purchase_audit_logs';
    public $timestamps = false;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'old_values',
        'new_values',
        'changes',
        'performed_by_id',
        'user_role',
        'ip_address',
        'user_agent',
        'reason',
        'notes',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changes' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('PurchaseAuditLog is append-only.');
        }
        return parent::save($options);
    }
}
