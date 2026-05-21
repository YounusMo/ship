<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Enums\MarginType;
use App\Modules\Purchases\Enums\RateStatus;
use App\Modules\Purchases\Events\ExchangeRateSpikeDetected;
use App\Modules\Purchases\Events\ExchangeRateUpdated;
use App\Modules\Purchases\Exceptions\ExchangeRateException;
use App\Modules\Purchases\Models\ExchangeRate;
use App\Modules\Purchases\Models\ExchangeRateConfig;
use App\Modules\Purchases\Support\MoneyHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * الخدمة الرئيسية لإدارة أسعار الصرف
 *
 * @see CLAUDE.md Section 5 - إدارة أسعار الصرف
 */
class ExchangeRateService
{
    private const CACHE_PREFIX = 'exchange_rate';
    private const CACHE_TTL = 21600; // 6 ساعات

    public function __construct(
        private readonly ExchangeRateFetcherService $fetcher,
    ) {
    }

    /**
     * احصل على السعر الحالي (مع cache)
     */
    public function getCurrentRate(string $from, string $to): ?string
    {
        if ($from === $to) {
            return '1';
        }

        $cacheKey = self::CACHE_PREFIX . ":{$from}:{$to}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($from, $to) {
            $rate = $this->getActiveRate($from, $to);
            return $rate?->effective_rate;
        });
    }

    /**
     * احصل على السعر النشط الحالي (model)
     */
    public function getActiveRate(string $from, string $to): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->where('status', RateStatus::ACTIVE)
            ->latest('valid_from')
            ->first();
    }

    /**
     * تحويل مبلغ من عملة لأخرى
     */
    public function convertAmount(string $amount, string $from, string $to): string
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = $this->getCurrentRate($from, $to);
        if ($rate === null) {
            throw ExchangeRateException::notAvailable($from, $to);
        }

        return MoneyHelper::mul($amount, $rate);
    }

    /**
     * تحديث السعر لإعداد معين (يُستخدم في الـ scheduler)
     */
    public function updateRate(ExchangeRateConfig $config): ExchangeRate
    {
        // جلب من الـ API
        $result = $this->fetcher->fetchWithFallback(
            $config->from_currency,
            $config->to_currency,
        );

        return $this->storeRate($config, $result);
    }

    /**
     * حفظ سعر جديد مع تطبيق الهامش وحماية القفزات
     *
     * @param  array{rate: string, source: string, fetched_at: \Carbon\Carbon}  $result
     */
    public function storeRate(ExchangeRateConfig $config, array $result): ExchangeRate
    {
        return DB::transaction(function () use ($config, $result) {
            $rawRate = $result['rate'];

            // طبّق الهامش
            $effectiveRate = MoneyHelper::applyMargin(
                $rawRate,
                $config->margin_type,
                (string) $config->margin_value,
            );

            $marginAmount = MoneyHelper::sub($effectiveRate, $rawRate, 8);

            // ─── Spike Protection ─────────────────────────────────
            $previousRate = $this->getActiveRate($config->from_currency, $config->to_currency);
            $requiresApproval = false;

            if ($previousRate !== null && $config->max_deviation_pct !== null) {
                $deviation = MoneyHelper::deviationPercent(
                    (string) $previousRate->raw_rate,
                    $rawRate,
                );

                if (MoneyHelper::gt($deviation, (string) $config->max_deviation_pct)) {
                    $requiresApproval = true;
                    Log::warning('Exchange rate spike detected', [
                        'pair' => $config->pair(),
                        'old_rate' => $previousRate->raw_rate,
                        'new_rate' => $rawRate,
                        'deviation_pct' => $deviation,
                        'max_allowed' => $config->max_deviation_pct,
                    ]);

                    event(new ExchangeRateSpikeDetected(
                        config: $config,
                        oldRate: (string) $previousRate->raw_rate,
                        newRate: $rawRate,
                        deviation: $deviation,
                    ));
                }
            }

            // ─── حفظ السعر الجديد ─────────────────────────────────

            // أبطل السعر السابق فقط إذا لم يكن السعر الجديد بانتظار موافقة
            if (!$requiresApproval && $previousRate !== null) {
                $previousRate->update([
                    'status' => RateStatus::SUPERSEDED,
                    'valid_until' => now(),
                ]);
            }

            $rate = ExchangeRate::create([
                'config_id' => $config->id,
                'from_currency' => $config->from_currency,
                'to_currency' => $config->to_currency,
                'raw_rate' => $rawRate,
                'raw_source' => $result['source'],
                'raw_fetched_at' => $result['fetched_at'],
                'margin_type' => $config->margin_type,
                'margin_value' => $config->margin_value,
                'margin_amount' => $marginAmount,
                'effective_rate' => $effectiveRate,
                'status' => $requiresApproval ? RateStatus::PENDING_APPROVAL : RateStatus::ACTIVE,
                'valid_from' => now(),
                'requires_approval' => $requiresApproval,
            ]);

            // امسح الـ cache فقط إذا السعر الجديد active
            if (!$requiresApproval) {
                Cache::forget(self::CACHE_PREFIX . ":{$config->from_currency}:{$config->to_currency}");

                event(new ExchangeRateUpdated($rate));
            }

            return $rate;
        });
    }

    /**
     * تعديل يدوي لسعر الصرف
     *
     * @see CLAUDE.md Section 5 - التعديل اليدوي
     */
    public function manualOverride(
        ExchangeRateConfig $config,
        string $newRate,
        string $reason,
        ?int $userId = null,
    ): ExchangeRate {
        if (strlen($reason) < 10) {
            throw new \App\Modules\Purchases\Exceptions\PurchaseException(
                message: 'Override reason must be at least 10 characters',
                messageAr: 'سبب التعديل يجب أن يكون 10 أحرف على الأقل',
            );
        }

        return DB::transaction(function () use ($config, $newRate, $reason, $userId) {
            // أبطل السعر النشط
            ExchangeRate::query()
                ->where('config_id', $config->id)
                ->where('status', RateStatus::ACTIVE)
                ->update([
                    'status' => RateStatus::SUPERSEDED,
                    'valid_until' => now(),
                ]);

            $rate = ExchangeRate::create([
                'config_id' => $config->id,
                'from_currency' => $config->from_currency,
                'to_currency' => $config->to_currency,
                'raw_rate' => $newRate,
                'raw_source' => 'manual',
                'raw_fetched_at' => now(),
                'margin_type' => MarginType::NONE,
                'margin_value' => 0,
                'margin_amount' => 0,
                'effective_rate' => $newRate,
                'status' => RateStatus::ACTIVE,
                'valid_from' => now(),
                'is_manual_override' => true,
                'override_by_id' => $userId ?? Auth::id(),
                'override_reason' => $reason,
            ]);

            Cache::forget(self::CACHE_PREFIX . ":{$config->from_currency}:{$config->to_currency}");

            event(new ExchangeRateUpdated($rate));

            return $rate;
        });
    }

    /**
     * الموافقة على سعر بانتظار الموافقة (spike)
     */
    public function approveRate(ExchangeRate $rate, ?int $approverId = null): ExchangeRate
    {
        if ($rate->status !== RateStatus::PENDING_APPROVAL) {
            throw new \App\Modules\Purchases\Exceptions\PurchaseException(
                message: 'Rate is not pending approval',
                messageAr: 'هذا السعر ليس بانتظار موافقة',
            );
        }

        return DB::transaction(function () use ($rate, $approverId) {
            // أبطل السعر النشط السابق
            ExchangeRate::query()
                ->where('config_id', $rate->config_id)
                ->where('status', RateStatus::ACTIVE)
                ->update([
                    'status' => RateStatus::SUPERSEDED,
                    'valid_until' => now(),
                ]);

            $rate->update([
                'status' => RateStatus::ACTIVE,
                'approved_by_id' => $approverId ?? Auth::id(),
                'approved_at' => now(),
            ]);

            Cache::forget(self::CACHE_PREFIX . ":{$rate->from_currency}:{$rate->to_currency}");

            event(new ExchangeRateUpdated($rate));

            return $rate->fresh();
        });
    }

    /**
     * رفض سعر بانتظار الموافقة
     */
    public function rejectRate(ExchangeRate $rate, string $reason, ?int $rejectorId = null): ExchangeRate
    {
        if ($rate->status !== RateStatus::PENDING_APPROVAL) {
            throw new \App\Modules\Purchases\Exceptions\PurchaseException(
                message: 'Rate is not pending approval',
                messageAr: 'هذا السعر ليس بانتظار موافقة',
            );
        }

        $rate->update([
            'status' => RateStatus::REJECTED,
            'approved_by_id' => $rejectorId ?? Auth::id(),
            'rejection_reason' => $reason,
        ]);

        return $rate->fresh();
    }
}
