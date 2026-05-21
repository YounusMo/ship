<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Enums;

/**
 * Mirrors the existing /api/shipments/{mode}/{id} route param. The
 * tracking layer is mode-agnostic — sea or sky shipments use the same
 * tracking_events table — but the mode is needed to resolve the
 * polymorphic source_table ('store_out_sea' or 'store_out_sky').
 */
enum ShipmentMode: string
{
    case SEA = 'sea';
    case SKY = 'sky';

    public function sourceTable(): string
    {
        return match ($this) {
            self::SEA => 'store_out_sea',
            self::SKY => 'store_out_sky',
        };
    }
}
