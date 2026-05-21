<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Events;

use App\Modules\Purchases\Models\ExchangeRateConfig;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExchangeRateSpikeDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ExchangeRateConfig $config,
        public readonly string $oldRate,
        public readonly string $newRate,
        public readonly string $deviation,
    ) {
    }
}
