<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $actor_type
 * @property int $actor_id
 * @property string $key
 * @property string $endpoint
 * @property int $response_status
 * @property string $response_body
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $expires_at
 */
class IdempotencyKey extends Model
{
    protected $table = 'tracking_idempotency_keys';
    public $timestamps = false;

    protected $fillable = [
        'actor_type', 'actor_id', 'key', 'endpoint',
        'response_status', 'response_body', 'created_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
