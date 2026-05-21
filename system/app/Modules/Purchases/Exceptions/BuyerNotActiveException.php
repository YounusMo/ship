<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Exceptions;

class BuyerNotActiveException extends PurchaseException
{
    protected string $errorCode = 'BUYER_NOT_ACTIVE';
}
