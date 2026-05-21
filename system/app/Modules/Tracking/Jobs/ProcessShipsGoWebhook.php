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

        // ShipsGo payload shapes vary slightly between ocean and air; the
        // top-level we care about is the list of events keyed under
        // 'events' (or a single event at the root for the v2 shape).
        $events = $this->extractEvents($payload);
        if ($events === []) {
            return 0;
        }

        $referenceId = $this->extractReferenceId($payload);
        if ($referenceId === null) {
            throw new \RuntimeException('ShipsGo webhook missing reference_id / tracking number');
        }

        [$sourceTable, $sourceId] = $this->resolveShipment($referenceId);

        $written = 0;
        foreach ($events as $i => $event) {
            $clientEventId = $this->buildEventId($delivery, $event, $i);

            try {
                DB::transaction(function () use (
                    $event, $sourceTable, $sourceId, $clientEventId,
                ) {
                    TrackingEvent::create([
                        'shipment_source_table' => $sourceTable,
                        'shipment_source_id'    => $sourceId,
                        'shipment_piece_id'     => null,
                        'kind'                  => TrackingEventKind::INTERNATIONAL,
                        'event_type'            => (string) ($event['event'] ?? $event['type'] ?? 'UNKNOWN'),
                        'occurred_at'           => $this->parseTimestamp($event),
                        'city'                  => $event['location']['city']    ?? $event['port'] ?? null,
                        'country'               => $event['location']['country'] ?? null,
                        'raw_payload'           => $event,
                        'translation_key'       => 'tracking::events.' . strtoupper((string) ($event['event'] ?? 'UNKNOWN')),
                        'translation_params'    => [
                            'city' => $event['location']['city'] ?? $event['port'] ?? '',
                        ],
                        'client_event_id'       => $clientEventId,
                        'is_customer_visible'   => true,
                    ]);
                });
                $written++;
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Duplicate — that's the idempotency guarantee working as
                // intended. Don't count, don't re-throw.
                continue;
            }
        }

        return $written;
    }

    /** @return array<int, array<string, mixed>> */
    private function extractEvents(array $payload): array
    {
        if (isset($payload['events']) && is_array($payload['events'])) {
            return array_values($payload['events']);
        }
        // Single-event shape — treat the payload itself as the event.
        if (isset($payload['event']) || isset($payload['type'])) {
            return [$payload];
        }
        return [];
    }

    private function extractReferenceId(array $payload): ?string
    {
        return $payload['reference_id']
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
        $providerEventId = $event['id'] ?? $event['event_id'] ?? null;
        if ($providerEventId !== null) {
            return "shipsgo:{$providerEventId}";
        }
        // Fall back to a hash that's stable across redeliveries of the
        // same event but distinct across genuinely different events.
        $material = $delivery->external_event_id . '|' . $index . '|' . json_encode($event);
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
