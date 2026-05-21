<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'product_name',
        'product_name_ar',
        'description',
        'product_url',
        'image_url',
        'supplier_name',
        'supplier_url',
        'quantity',
        'unit_price',
        'estimated_amount',
        'actual_amount',
        'currency',
        'color',
        'size',
        'variant',
        'specifications',
        'weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'volume_m3',
        'item_status',
        'received_qty',
        'received_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'estimated_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'specifications' => 'array',
            'weight_kg' => 'decimal:3',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'volume_m3' => 'decimal:6',
            'received_qty' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
