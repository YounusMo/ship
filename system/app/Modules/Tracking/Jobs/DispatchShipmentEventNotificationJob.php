<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Jobs;

use App\Models\Client;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Notifications\ShipmentTrackingEventReceived;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Fan-out: for a newly-written tracking event, find every customer with a
 * shipment in the same container and send them ShipmentTrackingEventReceived.
 *
 * Runs async on the queue so webhook processing returns fast even when
 * a container has many customers. Idempotent on re-run because the job
 * fetches fresh client_ids each invocation — duplicate notifications are
 * a UX cost the queue driver's retry policy keeps small but not zero.
 *
 * Skipped scenarios (return without throwing):
 *   - The event row was deleted between dispatch and execution.
 *   - The event is flagged is_customer_visible = false (e.g. SHIPMENT_DELETED).
 *   - The container has no store_out_* rows yet (unassigned container,
 *     or pure-supplier container with no customer-facing shipments).
 */
class DispatchShipmentEventNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $trackingEventId) {}

    public function handle(): void
    {
        $event = TrackingEvent::query()->find($this->trackingEventId);
        if (! $event || ! $event->is_customer_visible) {
            return;
        }

        $outTable = $this->outTableFor($event->shipment_source_table);
        if ($outTable === null) {
            // Internal-only event (custody-table source) — no container fan-out.
            // INTERNAL events are scoped to a specific shipment_piece and
            // ShipmentPieceController owns notifying that one customer directly.
            return;
        }

        $clientIds = DB::table($outTable)
            ->where('container_id', $event->shipment_source_id)
            ->where(function ($q) {
                $q->whereNull('canceled')->orWhere('canceled', '!=', 'true');
            })
            ->pluck('client_id')
            ->unique()
            ->filter(fn ($id) => $id !== null && $id > 0)
            ->values()
            ->all();

        if (empty($clientIds)) {
            return;
        }

        $clients = Client::query()->whereIn('id', $clientIds)->get();
        if ($clients->isEmpty()) {
            return;
        }

        // Route through NotificationService so clients with
        // notify_shipments = false are skipped, and outcomes are logged
        // uniformly. Failures inside the service are logged and absorbed
        // — the database channel still records the in-app feed entry,
        // and re-running the job would just duplicate entries.
        app(NotificationService::class)->notifyClients(
            $clients,
            NotificationService::KIND_SHIPMENTS,
            ShipmentTrackingEventReceived::fromEvent($event),
        );
    }

    /**
     * Map a container source_table to the matching store_out_* table that
     * holds per-customer rows for that container.
     */
    private function outTableFor(string $sourceTable): ?string
    {
        return match ($sourceTable) {
            'containers_sea' => 'store_out_sea',
            'containers_sky' => 'store_out_sky',
            default          => null,
        };
    }
}
