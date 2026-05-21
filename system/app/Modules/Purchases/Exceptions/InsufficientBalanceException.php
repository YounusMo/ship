<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class InsufficientBalanceException extends PurchaseException
{
    protected string $errorCode = 'INSUFFICIENT_BALANCE';
    protected int $statusCode = Response::HTTP_BAD_REQUEST;

    public static function forCustomer(string $required, string $available, string $currency): self
    {
        return new self(
            message: "Insufficient balance: required {$required} {$currency}, available {$available} {$currency}",
            messageAr: "الرصيد غير كافي: المطلوب {$required} {$currency}، المتاح {$available} {$currency}",
            context: compact('required', 'available', 'currency'),
        );
    }

    public static function forBuyer(int $buyerId, string $required, string $available): self
    {
        return new self(
            message: "Buyer {$buyerId} has insufficient float: required {$required} USD, available {$available} USD",
            messageAr: "رصيد عُهدة المسؤول غير كافي: المطلوب {$required} USD، المتاح {$available} USD",
            context: compact('buyerId', 'required', 'available'),
            errorCode: 'INSUFFICIENT_BUYER_FLOAT',
        );
    }
}
