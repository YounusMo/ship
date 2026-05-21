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

        // ShipsGo v2 webhook envelope shape:
        //   { event: {id, name, triggered_by}, shipment: {id, reference,
        //     container_number, booking_number, ...} }
        // The "event_type" we record on tracking_events is the event.name
        // (e.g. OCEAN.SHIPMENTS.CONTAINER_LOADED). Legacy multi-event shape
        // ({events: [...]}) is still handled defensively.
        $events = $this->extractEvents($payload);
        if ($events === []) {
            return 0;
        }

        $referenceId = $this->extractReferenceId($payload);
        if ($referenceId === null) {
            throw new \RuntimeException('ShipsGo webhook missing container_number / awb / reference');
        }

        [$sourceTable, $sourceId] = $this->resolveShipment($referenceId);

        $written = 0;
        foreach ($events as $i => $event) {
            $clientEventId = $this->buildEventId($delivery, $event, $i);
            $eventType = $this->extractEventType($event, $payload);

            try {
                DB::transaction(function () use (
                    $event, $payload, $sourceTable, $sourceId, $clientEventId, $eventType,
                ) {
                    TrackingEvent::create([
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
                        'is_customer_visible'   => true,
                    ]);
                });
                $written++;
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Duplicate — the idempotency guarantee working as intended.
                continue;
            }
        }

        return $written;
    }

    /** @return array<int, array<string, mixed>> */
    private function extractEvents(array $payload): array
    {
        // v2 envelope: top-level 'event' is a single event-meta object;
        // shipment-level events are embedded under 'shipment.events' when
        // the webhook is event-update flavored, OR the envelope itself
        // IS the event for create/discard webhooks.
        if (isset($payload['shipment']['events']) && is_array($payload['shipment']['events'])) {
            return array_values($payload['shipment']['events']);
        }
        if (isset($payload['events']) && is_array($payload['events'])) {
            return array_values($payload['events']);
        }
        // Single-event envelope (create / discard / status-change) — treat
        // the envelope itself as one event row.
        if (isset($payload['event']) || isset($payload['type'])) {
            return [$payload];
        }
        return [];
    }

    private function extractEventType(array $event, array $envelope): string
    {
        // v2 event-meta lives at envelope.event.name when the event row
        // is the envelope. Per-row events under shipment.events carry
        // their own 'name' or 'event' field. Skip values that are arrays
        // — that's the envelope's event-meta object, not the event name.
        foreach ([
            $event['name'] ?? null,
            // event['event'] may be a string (legacy) OR an array (v2 envelope) — only accept strings.
            is_string($event['event'] ?? null) ? $event['event'] : null,
            $event['type'] ?? null,
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
