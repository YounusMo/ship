<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $external_event_id
 * @property string|null $event_type
 * @property array $payload
 * @property string|null $signature
 * @property bool $signature_verified
 * @property \Carbon\Carbon $received_at
 * @property \Carbon\Carbon|null $processed_at
 * @property string|null $processing_error
 * @property int $attempt_count
 */
class WebhookDelivery extends Model
{
    protected $table = 'webhook_deliveries';

    protected $fillable = [
        'provider', 'external_event_id', 'event_type', 'payload',
        'signature', 'signature_verified',
        'received_at', 'processed_at', 'processing_error', 'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'payload'            => 'array',
            'signature_verified' => 'boolean',
            'received_at'        => 'datetime',
            'processed_at'       => 'datetime',
        ];
    }
}
