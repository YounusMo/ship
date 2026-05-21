<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class ExchangeRateException extends PurchaseException
{
    public static function notAvailable(string $from, string $to): self
    {
        return new self(
            message: "No active exchange rate for {$from} → {$to}",
            messageAr: "لا يوجد سعر صرف نشط للزوج {$from} → {$to}",
            context: ['from' => $from, 'to' => $to],
            statusCode: Response::HTTP_NOT_FOUND,
            errorCode: 'EXCHANGE_RATE_NOT_AVAILABLE',
        );
    }

    public static function fetchFailed(string $provider, string $error): self
    {
        return new self(
            message: "Failed to fetch from {$provider}: {$error}",
            messageAr: "فشل جلب سعر الصرف من {$provider}: {$error}",
            context: ['provider' => $provider, 'error' => $error],
            statusCode: Response::HTTP_SERVICE_UNAVAILABLE,
            errorCode: 'EXCHANGE_RATE_FETCH_FAILED',
        );
    }

    public static function spikeDetected(
        string $pair,
        float $oldRate,
        float $newRate,
        float $deviation,
    ): self {
        $devStr = number_format($deviation, 2);
        return new self(
            message: "Rate spike detected for {$pair}: {$oldRate} → {$newRate} ({$devStr}% deviation)",
            messageAr: "تغير حاد في سعر الصرف لـ {$pair}: {$oldRate} → {$newRate} (انحراف {$devStr}%)",
            context: compact('pair', 'oldRate', 'newRate', 'deviation'),
            statusCode: Response::HTTP_CONFLICT,
            errorCode: 'EXCHANGE_RATE_SPIKE',
        );
    }
}
