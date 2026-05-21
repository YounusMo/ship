<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base exception للموديول
 * كل الـ exceptions ترث منها
 */
class PurchaseException extends Exception
{
    protected string $errorCode = 'PURCHASE_ERROR';
    protected int $statusCode = Response::HTTP_BAD_REQUEST;
    protected string $messageAr = 'حدث خطأ';
    protected array $context = [];

    public function __construct(
        string $message = '',
        ?string $messageAr = null,
        array $context = [],
        ?int $statusCode = null,
        ?string $errorCode = null,
    ) {
        parent::__construct($message);

        if ($messageAr !== null) {
            $this->messageAr = $messageAr;
        }

        $this->context = $context;

        if ($statusCode !== null) {
            $this->statusCode = $statusCode;
        }

        if ($errorCode !== null) {
            $this->errorCode = $errorCode;
        }
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getMessageAr(): string
    {
        return $this->messageAr;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function toArray(): array
    {
        return [
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'message_ar' => $this->messageAr,
            'context' => $this->context,
        ];
    }

    /**
     * تحويل لـ JSON Response عند استخدامها في API
     */
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $this->toArray(),
        ], $this->statusCode);
    }
}
