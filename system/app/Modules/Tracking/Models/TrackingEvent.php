<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Models\User;
use App\Modules\Tracking\Enums\TrackingEventKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $shipment_source_table
 * @property int $shipment_source_id
 * @property int|null $shipment_piece_id
 * @property TrackingEventKind $kind
 * @property string $event_type
 * @property \Carbon\Carbon $occurred_at
 * @property string|null $city
 * @property string|null $country
 * @property int|null $branch_id
 * @property array|null $raw_payload
 * @property string|null $translation_key
 * @property array|null $translation_params
 * @property int|null $recorded_by_user_id
 * @property string|null $client_event_id
 * @property bool $is_customer_visible
 */
class TrackingEvent extends Model
{
    protected $table = 'tracking_events';

    protected $fillable = [
        'shipment_source_table', 'shipment_source_id', 'shipment_piece_id',
        'kind', 'event_type', 'occurred_at',
        'city', 'country', 'branch_id',
        'raw_payload', 'translation_key', 'translation_params',
        'recorded_by_user_id', 'client_event_id', 'is_customer_visible',
    ];

    protected function casts(): array
    {
        return [
            'kind'                => TrackingEventKind::class,
            'occurred_at'         => 'datetime',
            'raw_payload'         => 'array',
            'translation_params'  => 'array',
            'is_customer_visible' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(ShipmentPieceRef::class, 'shipment_piece_id');
    }

    public function scopeForShipment(Builder $q, string $sourceTable, int $sourceId): Builder
    {
        return $q->where('shipment_source_table', $sourceTable)
                 ->where('shipment_source_id', $sourceId);
    }

    public function scopeCustomerVisible(Builder $q): Builder
    {
        return $q->where('is_customer_visible', true);
    }
}
