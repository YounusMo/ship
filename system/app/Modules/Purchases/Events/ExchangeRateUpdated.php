<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Events;

use App\Modules\Purchases\Models\ExchangeRate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExchangeRateUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ExchangeRate $rate,
    ) {
    }
}
