<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Exceptions\ExchangeRateException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * مسؤول عن جلب أسعار الصرف من الـ APIs الخارجية
 *
 * Primary: Open Exchange Rates (مدفوع، USD as base on free tier)
 * Fallback: Frankfurter (مجاني، ECB rates)
 *
 * @see CLAUDE.md Section 5 - إدارة أسعار الصرف
 */
class ExchangeRateFetcherService
{
    public function __construct(
        private readonly string $oxrApiKey,
        private readonly string $oxrBaseUrl,
        private readonly string $frankfurterBaseUrl,
        private readonly int $timeout = 10,
    ) {
    }

    /**
     * @return array{from: string, to: string, rate: string, source: string, fetched_at: \Carbon\Carbon}
     */
    public function fetchWithFallback(string $from, string $to): array
    {
        try {
            return $this->fetchFromOpenExchangeRates($from, $to);
        } catch (ExchangeRateException $primary) {
            Log::warning('Primary exchange rate provider failed, trying fallback', [
                'from' => $from,
                'to' => $to,
                'error' => $primary->getMessage(),
            ]);

            try {
                return $this->fetchFromFrankfurter($from, $to);
            } catch (ExchangeRateException $fallback) {
                Log::error('Both exchange rate providers failed', [
                    'from' => $from,
                    'to' => $to,
                    'primary' => $primary->getMessage(),
                    'fallback' => $fallback->getMessage(),
                ]);

                throw ExchangeRateException::fetchFailed(
                    'all',
                    "Primary: {$primary->getMessage()} | Fallback: {$fallback->getMessage()}",
                );
            }
        }
    }

    /**
     * Open Exchange Rates (Primary)
     * Free tier supports USD as base only
     *
     * @return array{from: string, to: string, rate: string, source: string, fetched_at: \Carbon\Carbon}
     */
    public function fetchFromOpenExchangeRates(string $from, string $to): array
    {
        if ($from !== 'USD') {
            throw ExchangeRateException::fetchFailed(
                'openexchangerates',
                "Free tier supports USD base only, got {$from}",
            );
        }

        try {
            $response = $this->httpClient()
                ->get("{$this->oxrBaseUrl}/latest.json", [
                    'app_id' => $this->oxrApiKey,
                    'symbols' => $to,
                ]);

            if ($response->failed()) {
                throw ExchangeRateException::fetchFailed(
                    'openexchangerates',
                    "HTTP {$response->status()}: {$response->body()}",
                );
            }

            $data = $response->json();

            if (!isset($data['rates'][$to])) {
                throw ExchangeRateException::fetchFailed(
                    'openexchangerates',
                    "No rate returned for {$to}",
                );
            }

            $rate = (string) $data['rates'][$to];
            $timestamp = $data['timestamp'] ?? time();

            Log::info('Fetched rate from Open Exchange Rates', [
                'from' => $from,
                'to' => $to,
                'rate' => $rate,
            ]);

            return [
                'from' => $from,
                'to' => $to,
                'rate' => $rate,
                'source' => 'openexchangerates',
                'fetched_at' => now()->setTimestamp($timestamp),
            ];
        } catch (\Throwable $e) {
            if ($e instanceof ExchangeRateException) {
                throw $e;
            }
            throw ExchangeRateException::fetchFailed('openexchangerates', $e->getMessage());
        }
    }

    /**
     * Frankfurter (Fallback)
     * مجاني، لا يحتاج API key
     *
     * @return array{from: string, to: string, rate: string, source: string, fetched_at: \Carbon\Carbon}
     */
    public function fetchFromFrankfurter(string $from, string $to): array
    {
        try {
            $response = $this->httpClient()
                ->get("{$this->frankfurterBaseUrl}/latest", [
                    'from' => $from,
                    'to' => $to,
                ]);

            if ($response->failed()) {
                throw ExchangeRateException::fetchFailed(
                    'frankfurter',
                    "HTTP {$response->status()}",
                );
            }

            $data = $response->json();

            if (!isset($data['rates'][$to])) {
                throw ExchangeRateException::fetchFailed(
                    'frankfurter',
                    "No rate returned for {$to}",
                );
            }

            Log::info('Fetched rate from Frankfurter', [
                'from' => $from,
                'to' => $to,
                'rate' => $data['rates'][$to],
            ]);

            return [
                'from' => $from,
                'to' => $to,
                'rate' => (string) $data['rates'][$to],
                'source' => 'frankfurter',
                'fetched_at' => now(),
            ];
        } catch (\Throwable $e) {
            if ($e instanceof ExchangeRateException) {
                throw $e;
            }
            throw ExchangeRateException::fetchFailed('frankfurter', $e->getMessage());
        }
    }

    /**
     * جلب أسعار متعددة دفعة واحدة
     *
     * @param  array<int, string>  $toCurrencies
     * @return array<int, array{from: string, to: string, rate: string, source: string, fetched_at: \Carbon\Carbon}>
     */
    public function fetchMultiple(string $from, array $toCurrencies): array
    {
        if ($from !== 'USD') {
            throw ExchangeRateException::fetchFailed(
                'openexchangerates',
                'Batch fetch requires USD base',
            );
        }

        $response = $this->httpClient()
            ->get("{$this->oxrBaseUrl}/latest.json", [
                'app_id' => $this->oxrApiKey,
                'symbols' => implode(',', $toCurrencies),
            ]);

        if ($response->failed()) {
            throw ExchangeRateException::fetchFailed(
                'openexchangerates',
                "HTTP {$response->status()}",
            );
        }

        $data = $response->json();
        $rates = $data['rates'] ?? [];
        $timestamp = $data['timestamp'] ?? time();

        $results = [];
        foreach ($toCurrencies as $to) {
            if (!isset($rates[$to])) {
                Log::warning("Missing rate in batch response", ['currency' => $to]);
                continue;
            }
            $results[] = [
                'from' => $from,
                'to' => $to,
                'rate' => (string) $rates[$to],
                'source' => 'openexchangerates',
                'fetched_at' => now()->setTimestamp($timestamp),
            ];
        }

        return $results;
    }

    private function httpClient(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->retry(3, 200, throw: false)
            ->withHeaders(['User-Agent' => 'ShipFlow/1.0']);
    }
}
