<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Jobs;

use App\Modules\Tracking\Enums\TrackingEventKind;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\WebhookDelivery;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Parses one webhook_deliveries row into one or more tracking_events.
 *
 * Idempotency: every TrackingEvent we insert carries a deterministic
 * client_event_id derived from the ShipsGo event id (or, when missing,
 * a hash of the row's identifying fields). The unique
 * tracking_events.(kind, client_event_id) index makes re-processing
 * safe — duplicate inserts fail and we move on.
 *
 * Failure handling: any exception marks the delivery row's
 * processing_error and bumps attempt_count, then re-throws so the queue
 * driver's retry policy kicks in.
 */
class ProcessShipsGoWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $webhookDeliveryId)
    {
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()->find($this->webhookDeliveryId);
        if (! $delivery) {
            return;
        }
        if ($delivery->processed_at !== null) {
            // Already done — safe to re-run idempotently.
            return;
        }

        try {
            $count = $this->processPayload($delivery);
            $delivery->update([
                'processed_at'     => now(),
                'processing_error' => null,
                'attempt_count'    => $delivery->attempt_count + 1,
            ]);
        } catch (Throwable $e) {
            $delivery->update([
                'processing_error' => mb_substr($e->getMessage(), 0, 5000),
                'attempt_count'    => $delivery->attempt_count + 1,
            ]);
            throw $e;
        }
    }

    private function processPayload(WebhookDelivery $delivery): int
    {
        $payload = $delivery->payload;

        // ShipsGo v2 webhook shape (confirmed against the dashboard at
        // shipsgo.com/dashboard/integrations/webhooks — the only three
        // subscribable ocean events are SHIPMENT_CREATED, SHIPMENT_UPDATED,
        // SHIPMENT_DELETED). Each delivery carries:
        //   {
        //     event:    { id, name, triggered_by },
        //     shipment: { id, reference, container_number, ..., containers: [
        //       { number, events: [{ type, timestamp, location, ... }, ...] }
        //     ]}
        //   }
        //
        // The per-leg events (GATE_IN, LOADED, ARRIVED, DISCHARGED, ...)
        // we surface to the customer live inside shipment.containers[].events[].
        // The envelope-level event.name is the WEBHOOK type, not the
        // tracking-event type — useful for ops dashboards but not what
        // the customer wants to see in their timeline.
        $referenceId = $this->extractReferenceId($payload);
        if ($referenceId === null) {
            throw new \RuntimeException('ShipsGo webhook missing container_number / awb / reference');
        }
        [$sourceTable, $sourceId] = $this->resolveShipment($referenceId);

        $envelopeEventName = (string) ($payload['event']['name'] ?? 'UNKNOWN');

        // SHIPMENT_DELETED — mark the shipment as discarded by emitting one
        // tracking row; no nested leg events to walk.
        if (str_ends_with($envelopeEventName, '.SHIPMENT_DELETED')) {
            return $this->writeOne(
                sourceTable: $sourceTable,
                sourceId   : $sourceId,
                event      : $payload,
                eventType  : 'SHIPMENT_DELETED',
                clientEventId: $this->buildEventId($delivery, $payload, 0),
                customerVisible: false,
            ) ? 1 : 0;
        }

        // SHIPMENT_CREATED / SHIPMENT_UPDATED — walk every container's
        // events array, each becomes one TrackingEvent.
        $legEvents = $this->extractLegEvents($payload);
        if ($legEvents === []) {
            // Fall back to the legacy single-event envelope (older deliveries
            // or third-party simulations).
            $legEvents = $this->extractLegacyEvents($payload);
        }

        $written = 0;
        $idx = 0;
        foreach ($legEvents as $legEvent) {
            $eventType    = $this->extractEventType($legEvent, $payload);
            $clientEventId = $this->buildEventId($delivery, $legEvent, $idx++);
            if ($this->writeOne(
                sourceTable: $sourceTable,
                sourceId   : $sourceId,
                event      : $legEvent,
                eventType  : $eventType,
                clientEventId: $clientEventId,
                customerVisible: true,
            )) {
                $written++;
            }
        }
        return $written;
    }

    /**
     * Insert one tracking_events row. Returns true on success, false on
     * unique-constraint violation (= idempotent dedup, expected).
     *
     * Side-effect: on a fresh insert with is_customer_visible = true,
     * dispatches DispatchShipmentEventNotificationJob to fan out an FCM
     * push to every customer with a shipment in the same container.
     * Dispatched *after* the transaction commits so a rolled-back insert
     * never triggers a phantom notification.
     *
     * @param  array<string, mixed>  $event
     */
    private function writeOne(
        string $sourceTable,
        int $sourceId,
        array $event,
        string $eventType,
        string $clientEventId,
        bool $customerVisible,
    ): bool {
        $created = null;
        try {
            DB::transaction(function () use (
                $event, $sourceTable, $sourceId, $clientEventId, $eventType, $customerVisible, &$created,
            ) {
                $created = TrackingEvent::create([
                    'shipment_source_table' => $sourceTable,
                    'shipment_source_id'    => $sourceId,
                    'shipment_piece_id'     => null,
                    'kind'                  => TrackingEventKind::INTERNATIONAL,
                    'event_type'            => $eventType,
                    'occurred_at'           => $this->parseTimestamp($event),
                    'city'                  => $event['location']['city']    ?? $event['port'] ?? null,
                    'country'               => $event['location']['country'] ?? null,
                    'raw_payload'           => $event,
                    'translation_key'       => 'tracking::events.' . strtoupper($eventType),
                    'translation_params'    => [
                        'city' => $event['location']['city'] ?? $event['port'] ?? '',
                    ],
                    'client_event_id'       => $clientEventId,
                    'is_customer_visible'   => $customerVisible,
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return false;
        }

        if ($created instanceof TrackingEvent && $customerVisible) {
            DispatchShipmentEventNotificationJob::dispatch($created->id)->afterCommit();
        }
        return true;
    }

    /**
     * v2 nested-events extractor. Flattens shipment.containers[*].events[*]
     * into a single list, tagging each event with the container number so
     * downstream consumers can attribute events to a specific container
     * (relevant for shipments that have more than one container).
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractLegEvents(array $payload): array
    {
        $containers = $payload['shipment']['containers'] ?? null;
        if (! is_array($containers) || $containers === []) {
            return [];
        }
        $out = [];
        foreach ($containers as $container) {
            if (! is_array($container)) continue;
            $containerNumber = $container['number'] ?? null;
            $events = $container['events'] ?? null;
            if (! is_array($events)) continue;
            foreach ($events as $event) {
                if (! is_array($event)) continue;
                // Stamp the container number onto the event so writeOne()'s
                // raw_payload retains attribution.
                $event['_container_number'] = $containerNumber;
                $out[] = $event;
            }
        }
        return $out;
    }

    /**
     * Legacy / simulation fallback: payloads that put events at
     * shipment.events[] or events[] or as a single envelope event.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractLegacyEvents(array $payload): array
    {
        if (isset($payload['shipment']['events']) && is_array($payload['shipment']['events'])) {
            return array_values($payload['shipment']['events']);
        }
        if (isset($payload['events']) && is_array($payload['events'])) {
            return array_values($payload['events']);
        }
        if (isset($payload['event']) || isset($payload['type'])) {
            return [$payload];
        }
        return [];
    }

    private function extractEventType(array $event, array $envelope): string
    {
        // v2 per-leg event uses 'type' (e.g. GATE_IN, LOADED, DISCHARGED).
        // Legacy / envelope-as-event uses 'event.name'. Skip arrays — that's
        // the envelope's event-meta object, not a string event type.
        foreach ([
            $event['type'] ?? null,
            $event['name'] ?? null,
            is_string($event['event'] ?? null) ? $event['event'] : null,
            $envelope['event']['name'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }
        return 'UNKNOWN';
    }

    private function extractReferenceId(array $payload): ?string
    {
        // v2 envelope keeps shipment identifiers under 'shipment'.
        return $payload['shipment']['container_number']
            ?? $payload['shipment']['awb_number']
            ?? $payload['shipment']['booking_number']
            ?? $payload['shipment']['reference']
            ?? $payload['reference_id']
            ?? $payload['reference']
            ?? $payload['tracking_number']
            ?? $payload['container_number']
            ?? $payload['awb']
            ?? null;
    }

    /**
     * Maps a ShipsGo reference (container number for ocean, AWB for air)
     * back to a ShipFlow CONTAINER, not a per-customer shipment row. The
     * legacy schema stores the upstream identifier on containers_sea.number
     * and containers_sky.number; multiple store_out_sea rows can share one
     * container (multi-customer container), so events live at the container
     * level. UnifiedTimelineService is responsible for joining
     * container-scoped INTERNATIONAL events with shipment-scoped INTERNAL
     * events at read time.
     *
     * @return array{0: string, 1: int}
     */
    private function resolveShipment(string $referenceId): array
    {
        foreach (['containers_sea', 'containers_sky'] as $table) {
            $id = DB::table($table)
                ->where('number', $referenceId)
                ->value('id');
            if ($id !== null) {
                return [$table, (int) $id];
            }
        }
        throw new \RuntimeException(
            "No container found for ShipsGo reference '{$referenceId}' — "
            . 'check containers_sea.number / containers_sky.number'
        );
    }

    private function buildEventId(WebhookDelivery $delivery, array $event, int $index): string
    {
        // Multi-event payloads: prefer the per-event id. Single-event
        // envelopes: the dedup id was already captured in the
        // WebhookDelivery.external_event_id (synthesized by the
        // controller when missing), so reuse it with an index suffix to
        // distinguish if a payload ever carries multiple events sharing
        // one envelope id.
        $providerEventId = $event['id'] ?? $event['event_id'] ?? null;
        if ($providerEventId !== null) {
            return "shipsgo:{$providerEventId}";
        }
        if ($delivery->external_event_id !== '') {
            return "shipsgo:{$delivery->external_event_id}:{$index}";
        }
        $material = $index . '|' . json_encode($event);
        return 'shipsgo:' . hash('sha256', $material);
    }

    private function parseTimestamp(array $event): Carbon
    {
        $ts = $event['timestamp']
            ?? $event['occurred_at']
            ?? $event['event_time']
            ?? null;
        if ($ts === null) {
            return Carbon::now();
        }
        try {
            return Carbon::parse((string) $ts);
        } catch (Throwable) {
            return Carbon::now();
        }
    }
}
