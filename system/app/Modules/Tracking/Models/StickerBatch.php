<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $batch_code
 * @property int $quantity
 * @property int $generated_by_user_id
 * @property \Carbon\Carbon $generated_at
 * @property string|null $pdf_path
 * @property string|null $notes
 */
class StickerBatch extends Model
{
    protected $table = 'sticker_batches';

    protected $fillable = [
        'batch_code', 'quantity', 'generated_by_user_id',
        'generated_at', 'pdf_path', 'notes',
    ];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    public function stickers(): HasMany
    {
        return $this->hasMany(Sticker::class, 'batch_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
