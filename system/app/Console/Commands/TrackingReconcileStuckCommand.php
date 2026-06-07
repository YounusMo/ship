<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Tracking\Enums\CustodyEventType;
use App\Modules\Tracking\Notifications\StuckShipmentReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 *   php artisan tracking:reconcile-stuck [--days=7]
 *
 * Reports shipment pieces whose most recent custody event hasn't moved
 * forward in N days. Useful as a daily cron — surfaces shipments that
 * physically went missing or got skipped in the scan flow.
 *
 * No state change is made; this is a read-only sweep. Pair with a
 * Notification dispatch in a Listener if you want push to the operator
 * (out of scope for this command).
 */
class TrackingReconcileStuckCommand extends Command
{
    protected $signature = 'tracking:reconcile-stuck
                            {--days=7 : Pieces with no custody change in this many days are flagged}
                            {--limit=200 : Cap on rows returned}
                            {--notify : Send StuckShipmentReminderNotification to admin / branch_admin users}';

    protected $description = 'List shipment pieces whose latest custody event is older than --days. Optionally notifies ops.';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subDays((int) $this->option('days'));
        $limit  = (int) $this->option('limit');

        // Subquery: latest custody row per (source_table, source_id, piece_id).
        $latestIds = DB::table('custody_events')
            ->select(DB::raw('MAX(id) AS id'))
            ->groupBy('shipment_source_table', 'shipment_source_id', 'shipment_piece_id');

        $rows = DB::table('custody_events')
            ->whereIn('custody_events.id', $latestIds)
            ->where('custody_events.occurred_at', '<', $cutoff)
            ->whereNotIn('custody_events.event_type', [
                CustodyEventType::DELIVERED_TO_CUSTOMER->value,
                CustodyEventType::LOST->value,
            ])
            ->orderBy('custody_events.occurred_at')
            ->limit($limit)
            ->get([
                'custody_events.id',
                'custody_events.shipment_source_table',
                'custody_events.shipment_source_id',
                'custody_events.shipment_piece_id',
                'custody_events.event_type',
                'custody_events.to_branch_id',
                'custody_events.occurred_at',
            ]);

        if ($rows->isEmpty()) {
            $this->info("No stuck pieces (cutoff {$cutoff->toIso8601String()}).");
            return self::SUCCESS;
        }

        $this->warn("Found {$rows->count()} stuck pieces (cutoff {$cutoff->toIso8601String()}):");
        $this->table(
            ['custody_id', 'source', 'piece_id', 'state', 'at_branch', 'since'],
            $rows->map(fn ($r) => [
                $r->id,
                "{$r->shipment_source_table}#{$r->shipment_source_id}",
                (string) ($r->shipment_piece_id ?? '—'),
                $r->event_type,
                (string) ($r->to_branch_id ?? '—'),
                (string) $r->occurred_at,
            ])->all(),
        );

        if ($this->option('notify')) {
            $admins = User::query()
                ->whereIn('type', ['admin', 'branch_admin'])
                ->where(function ($q) {
                    $q->whereNull('not_active')->orWhere('not_active', '!=', 'true');
                })
                ->where(function ($q) {
                    $q->whereNull('deleted')->orWhere('deleted', '!=', 'true');
                })
                ->get();

            if ($admins->isEmpty()) {
                $this->warn('No active admin / branch_admin users to notify.');
            } else {
                $stuckPayload = $rows->map(fn ($r) => [
                    'custody_id' => (int) $r->id,
                    'source'     => "{$r->shipment_source_table}#{$r->shipment_source_id}",
                    'piece_id'   => $r->shipment_piece_id !== null ? (int) $r->shipment_piece_id : null,
                    'state'      => $r->event_type,
                    'branch_id'  => $r->to_branch_id !== null ? (int) $r->to_branch_id : null,
                    'since'      => (string) $r->occurred_at,
                ])->all();

                Notification::send(
                    $admins,
                    new StuckShipmentReminderNotification(
                        stuckPieces : $stuckPayload,
                        cutoffDays  : (int) $this->option('days'),
                        cutoffAt    : $cutoff->toIso8601String(),
                    ),
                );
                $this->info("Notified {$admins->count()} admin user(s).");
            }
        }

        return self::SUCCESS;
    }
}
