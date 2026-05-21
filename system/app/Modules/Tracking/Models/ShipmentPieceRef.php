<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lightweight Eloquent ref to the existing shipment_pieces table. We do
 * NOT own this table (it's part of the legacy shipment surface) — just
 * read it for tracking joins. Mass assignment intentionally disabled to
 * keep writes flowing through the legacy code path.
 *
 * @property int $id
 * @property string $tracking_code
 * @property string $source_table
 * @property int $source_id
 * @property int $client_id
 * @property int $piece_index
 * @property int $piece_total
 * @property string $status
 */
class ShipmentPieceRef extends Model
{
    protected $table = 'shipment_pieces';
    protected $guarded = ['id'];
}
