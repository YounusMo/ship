<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id ULID
 * @property int $batch_id
 * @property int|null $shipment_piece_id
 * @property \Carbon\Carbon|null $printed_at
 * @property \Carbon\Carbon|null $assigned_at
 * @property \Carbon\Carbon|null $revoked_at
 * @property string|null $revoke_reason
 */
class Sticker extends Model
{
    use HasUlids;

    protected $table = 'stickers';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'batch_id', 'shipment_piece_id',
        'printed_at', 'assigned_at', 'revoked_at', 'revoke_reason',
    ];

    protected function casts(): array
    {
        return [
            'printed_at'  => 'datetime',
            'assigned_at' => 'datetime',
            'revoked_at'  => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StickerBatch::class, 'batch_id');
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(ShipmentPieceRef::class, 'shipment_piece_id');
    }

    public function isAssigned(): bool
    {
        return $this->assigned_at !== null && $this->revoked_at === null;
    }

    public function qrPayload(): string
    {
        return "shipflow://qr/{$this->id}";
    }
}
