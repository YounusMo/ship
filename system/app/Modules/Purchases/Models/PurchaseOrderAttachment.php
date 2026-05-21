<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderAttachment extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_attachments';
    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id',
        'type',
        'file_name',
        'file_path',
        'file_url',
        'file_size',
        'mime_type',
        'description',
        'uploaded_by_id',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
