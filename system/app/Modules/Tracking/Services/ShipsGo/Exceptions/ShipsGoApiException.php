<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services\ShipsGo\Exceptions;

use RuntimeException;
use Throwable;

class ShipsGoApiException extends RuntimeException
{
    public static function transport(string $message, ?Throwable $prev = null): self
    {
        return new self("ShipsGo transport error: {$message}", 0, $prev);
    }

    public static function http(int $status, string $body): self
    {
        $excerpt = mb_substr($body, 0, 500);
        return new self("ShipsGo HTTP {$status}: {$excerpt}");
    }

    public static function invalidPayload(string $why): self
    {
        return new self("ShipsGo payload invalid: {$why}");
    }
}
