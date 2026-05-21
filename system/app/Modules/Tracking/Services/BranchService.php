<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Tracking\Models\Branch;
use App\Modules\Tracking\Models\BranchStaff;
use App\Modules\Tracking\Models\CustodyEvent;
use App\Modules\Tracking\Enums\CustodyEventType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Reads about branches + custody routing. Cached lightly per-request so
 * scan-heavy endpoints don't re-hit the DB for the same lookups.
 */
class BranchService
{
    /** @return Collection<int, Branch> */
    public function activeBranches(): Collection
    {
        return Cache::remember('tracking.branches.active', 60, function () {
            return Branch::active()->get();
        });
    }

    /** @return array<int, string>  Sanctum "branch:N" abilities for a user. */
    public function abilitiesForUser(int $userId): array
    {
        $rows = BranchStaff::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('branch_id')
            ->all();

        return array_map(static fn (int $id) => "branch:{$id}", $rows);
    }

    /**
     * Current custody holder — the to_branch_id of the latest non-terminal
     * custody_events row for this shipment piece (or shipment if pieceId
     * is null). Returns null when no custody has been recorded yet.
     */
    public function currentCustody(string $sourceTable, int $sourceId, ?int $pieceId = null): ?CustodyEvent
    {
        $q = CustodyEvent::query()
            ->where('shipment_source_table', $sourceTable)
            ->where('shipment_source_id', $sourceId)
            ->whereNotIn('event_type', [
                CustodyEventType::DELIVERED_TO_CUSTOMER->value,
                CustodyEventType::LOST->value,
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($pieceId !== null) {
            $q->where(function ($qq) use ($pieceId) {
                $qq->whereNull('shipment_piece_id')
                   ->orWhere('shipment_piece_id', $pieceId);
            });
        }

        return $q->first();
    }
}
