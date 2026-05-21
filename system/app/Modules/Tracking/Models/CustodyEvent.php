<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Models\User;
use App\Modules\Tracking\Enums\CustodyEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $shipment_source_table
 * @property int $shipment_source_id
 * @property int|null $shipment_piece_id
 * @property CustodyEventType $event_type
 * @property int|null $from_branch_id
 * @property int|null $to_branch_id
 * @property int $recorded_by_user_id
 * @property \Carbon\Carbon $occurred_at
 * @property array|null $photos
 * @property string|null $notes
 * @property int|null $tracking_event_id
 */
class CustodyEvent extends Model
{
    protected $table = 'custody_events';

    protected $fillable = [
        'shipment_source_table', 'shipment_source_id', 'shipment_piece_id',
        'event_type', 'from_branch_id', 'to_branch_id',
        'recorded_by_user_id', 'occurred_at', 'photos', 'notes',
        'tracking_event_id',
    ];

    protected function casts(): array
    {
        return [
            'event_type'  => CustodyEventType::class,
            'occurred_at' => 'datetime',
            'photos'      => 'array',
        ];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function trackingEvent(): BelongsTo
    {
        return $this->belongsTo(TrackingEvent::class, 'tracking_event_id');
    }
}
