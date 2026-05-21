<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Http\Resources;

use App\Modules\Purchases\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrderItem
 */
class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'product_name_ar' => $this->product_name_ar,
            'description' => $this->description,
            'product_url' => $this->product_url,
            'image_url' => $this->image_url,
            'supplier_name' => $this->supplier_name,
            'supplier_url' => $this->supplier_url,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'currency' => $this->currency,
            'estimated_amount' => $this->estimated_amount,
            'actual_amount' => $this->actual_amount,
            'specs' => [
                'color' => $this->color,
                'size' => $this->size,
                'variant' => $this->variant,
                'weight_kg' => $this->weight_kg,
                'volume_m3' => $this->volume_m3,
            ],
            'status' => $this->item_status,
            'received_qty' => $this->received_qty,
            'received_at' => $this->received_at,
            'notes' => $this->notes,
        ];
    }
}
