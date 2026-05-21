<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Enums\CustodyEventType;
use App\Modules\Tracking\Models\CustodyEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Returns the live queue at a given branch — distinct shipment pieces
 * whose current custody is "in this branch's hands" (i.e., the latest
 * non-terminal custody_events row has to_branch_id = $branch).
 *
 * Backed by an in-PHP filter over the latest custody row per piece —
 * pragmatic given the small per-branch volume. A future window-function
 * query can replace this if the queue grows.
 */
class BranchQueueController extends Controller
{
    public function __invoke(Request $request, int $branch)
    {
        $live = collect(CustodyEventType::cases())
            ->reject(fn ($c) => in_array(
                $c,
                [CustodyEventType::DELIVERED_TO_CUSTOMER, CustodyEventType::LOST],
                true,
            ))
            ->map(fn ($c) => $c->value)
            ->values();

        // Latest custody per piece (or per shipment when no piece).
        $sub = DB::table('custody_events')
            ->select(DB::raw('MAX(id) AS latest_id'))
            ->groupBy('shipment_source_table', 'shipment_source_id', 'shipment_piece_id');

        $rows = CustodyEvent::query()
            ->whereIn('id', $sub)
            ->where('to_branch_id', $branch)
            ->whereIn('event_type', $live->all())
            ->orderByDesc('occurred_at')
            ->limit((int) $request->query('limit', 100))
            ->get();

        return response()->json([
            'branch_id' => $branch,
            'count'     => $rows->count(),
            'items'     => $rows,
        ]);
    }
}
