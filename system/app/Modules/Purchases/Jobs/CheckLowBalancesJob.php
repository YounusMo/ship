<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Jobs;

use App\Modules\Purchases\Events\BuyerLowBalanceDetected;
use App\Modules\Purchases\Models\BuyerAccount;
use App\Modules\Purchases\Support\MoneyHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * فحص يومي لأرصدة العُهد وإطلاق تنبيهات
 */
class CheckLowBalancesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        BuyerAccount::query()
            ->where('is_active', true)
            ->with('buyer')
            ->chunk(100, function ($accounts) {
                foreach ($accounts as $account) {
                    $available = $account->availableBalance();
                    if (MoneyHelper::lt($available, (string) $account->min_threshold)) {
                        event(new BuyerLowBalanceDetected($account, (string) $account->min_threshold));
                    }
                }
            });
    }
}
