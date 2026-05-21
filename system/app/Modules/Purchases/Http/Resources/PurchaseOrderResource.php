<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Http\Resources;

use App\Modules\Purchases\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,

            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer'),

            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse'),

            'buyer_id' => $this->buyer_id,
            'buyer' => $this->whenLoaded('buyer'),

            'currencies' => [
                'purchase' => $this->purchase_currency,
                'customer' => $this->customer_currency,
            ],

            'exchange_rate' => [
                'frozen' => $this->frozen_exchange_rate,
                'frozen_at' => $this->exchange_rate_frozen_at,
            ],

            'amounts' => [
                'estimated_purchase' => $this->estimated_purchase_amount,
                'estimated_total_usd' => $this->estimated_total_usd,
                'actual_purchase' => $this->actual_purchase_amount,
                'actual_total_usd' => $this->actual_total_usd,
                'customer_charged_usd' => $this->customer_charged_usd,
            ],

            'commission' => [
                'type' => $this->commission_type,
                'type_label' => $this->commission_type->label(),
                'value' => $this->commission_value,
                'amount' => $this->commission_amount,
                'notes' => $this->commission_notes,
            ],

            'status' => [
                'value' => $this->status,
                'label' => $this->status->label(),
                'is_terminal' => $this->status->isTerminal(),
                'is_cancellable' => $this->status->isCancellable(),
                'is_ready_for_shipment' => $this->isReadyForShipment(),
            ],

            'shipment' => [
                'shipment_id' => $this->shipment_id,
                'container_id' => $this->container_id,
                'added_to_shipment_at' => $this->added_to_shipment_at,
            ],

            'supplier' => [
                'name' => $this->supplier_name,
                'contact' => $this->supplier_contact,
                'tracking_number' => $this->tracking_number,
            ],

            'notes' => [
                'customer' => $this->customer_notes,
                'internal' => $this->internal_notes,
            ],
            'contact_source' => $this->contact_source,

            'timeline' => [
                'requested_at' => $this->requested_at,
                'confirmed_at' => $this->confirmed_at,
                'purchasing_started_at' => $this->purchasing_started_at,
                'purchased_at' => $this->purchased_at,
                'received_at' => $this->received_at,
                'shipped_at' => $this->shipped_at,
                'delivered_at' => $this->delivered_at,
                'cancelled_at' => $this->cancelled_at,
            ],

            'cancellation' => $this->when($this->cancelled_at !== null, [
                'reason' => $this->cancellation_reason,
                'cancelled_by_id' => $this->cancelled_by_id,
            ]),

            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'attachments' => $this->whenLoaded('attachments'),
            'status_history' => $this->whenLoaded('statusHistory'),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
