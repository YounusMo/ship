<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerReconciliation extends Model
{
    use HasFactory;

    protected $table = 'buyer_reconciliations';

    protected $fillable = [
        'buyer_account_id',
        'period_start',
        'period_end',
        'system_balance',
        'actual_balance',
        'difference',
        'difference_reason',
        'adjustment_tx_id',
        'attachment_url',
        'reconciled_by_id',
        'approved_by_id',
        'status',
        'notes',
        'rejection_reason',
        'reconciled_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'system_balance' => 'decimal:2',
            'actual_balance' => 'decimal:2',
            'difference' => 'decimal:2',
            'reconciled_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function buyerAccount(): BelongsTo
    {
        return $this->belongsTo(BuyerAccount::class);
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function adjustmentTransaction(): BelongsTo
    {
        return $this->belongsTo(BuyerTransaction::class, 'adjustment_tx_id');
    }
}
