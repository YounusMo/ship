<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Events;

use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Models\PurchaseOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly PurchaseOrder $order,
        public readonly PurchaseOrderStatus $fromStatus,
        public readonly PurchaseOrderStatus $toStatus,
    ) {
    }
}
