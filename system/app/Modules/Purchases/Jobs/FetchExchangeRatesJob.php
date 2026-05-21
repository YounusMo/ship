<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Jobs;

use App\Modules\Purchases\Models\ExchangeRateConfig;
use App\Modules\Purchases\Services\ExchangeRateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * تحديث أسعار الصرف من الـ APIs
 *
 * يُجدول كل 6 ساعات في bootstrap/app.php
 *
 * @see CLAUDE.md Section 5 - Background Jobs
 */
class FetchExchangeRatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 60;

    public function handle(ExchangeRateService $service): void
    {
        $configs = ExchangeRateConfig::query()
            ->where('is_active', true)
            ->where('auto_update', true)
            ->get();

        Log::info("Fetching exchange rates for {$configs->count()} pairs");

        $successful = 0;
        $failed = 0;

        foreach ($configs as $config) {
            try {
                $service->updateRate($config);
                $successful++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Failed to update exchange rate', [
                    'pair' => $config->pair(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Exchange rates update completed", [
            'successful' => $successful,
            'failed' => $failed,
        ]);
    }
}
