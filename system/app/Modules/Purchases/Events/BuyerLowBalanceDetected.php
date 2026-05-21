<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Events;

use App\Modules\Purchases\Models\BuyerAccount;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BuyerLowBalanceDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly BuyerAccount $account,
        public readonly string $threshold,
    ) {
    }
}
