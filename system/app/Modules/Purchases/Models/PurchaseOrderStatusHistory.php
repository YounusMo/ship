<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Models\User;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only model
 */
class PurchaseOrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_status_history';

    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id',
        'from_status',
        'to_status',
        'reason',
        'notes',
        'performed_by_id',
        'ip_address',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => PurchaseOrderStatus::class,
            'to_status' => PurchaseOrderStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('PurchaseOrderStatusHistory is append-only.');
        }
        return parent::save($options);
    }
}
