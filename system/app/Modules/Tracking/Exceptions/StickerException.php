<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Exceptions;

use RuntimeException;

class StickerException extends RuntimeException
{
    public static function notFound(string $stickerId): self
    {
        return new self("Sticker {$stickerId} not found");
    }

    public static function revoked(string $stickerId): self
    {
        return new self("Sticker {$stickerId} is revoked and cannot be assigned");
    }

    public static function alreadyAssigned(string $stickerId, int $existingPieceId): self
    {
        return new self(
            "Sticker {$stickerId} is already assigned to shipment piece {$existingPieceId}",
        );
    }

    public static function alreadyRevoked(string $stickerId): self
    {
        return new self("Sticker {$stickerId} is already revoked");
    }
}
