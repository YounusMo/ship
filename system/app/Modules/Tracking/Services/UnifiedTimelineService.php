<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Tracking\Enums\ShipmentMode;
use App\Modules\Tracking\Enums\TrackingEventKind;
use App\Modules\Tracking\Models\TrackingEvent;
use Illuminate\Support\Collection;

/**
 * Merges INTERNATIONAL (ShipsGo) and INTERNAL (employee scans) events for
 * one shipment into a single chronological, localized, customer-safe
 * stream. The Flutter app sees ONE timeline; the dual sourcing is
 * invisible.
 *
 * Localization is applied here, on the backend, via translation_key +
 * translation_params — so the wire payload is ready-to-render.
 *
 * White-label sanitization (stripping provider names like "shipsgo") is
 * not done here — it's the EnforceMobileSanitization middleware's job,
 * which runs after the resource is serialized. Keeps responsibility in
 * one place.
 */
class UnifiedTimelineService
{
    public function __construct(
        private readonly StatusComputer $statusComputer,
    ) {
    }

    /**
     * Build the unified timeline for a customer-facing shipment view.
     *
     * Why containerId is separate from shipmentId: INTERNATIONAL events
     * (ShipsGo) land at the CONTAINER level — multiple store_out_* rows
     * can share one container (multi-customer container), so the upstream
     * provider only knows about the container. INTERNAL events live at
     * the per-shipment level. We merge both into one stream here so the
     * Flutter app sees a single chronological timeline.
     *
     * @return array{
     *   status: string,
     *   timeline: list<array<string, mixed>>,
     *   counts: array{international: int, internal: int}
     * }
     */
    public function for(
        ShipmentMode $mode,
        int $shipmentId,
        ?int $containerId = null,
        ?int $pieceId = null,
        ?string $locale = null,
    ): array {
        $events = $this->loadEvents($mode, $shipmentId, $containerId, $pieceId);

        return [
            'status'   => $this->statusComputer->derive($events),
            'timeline' => $events
                ->sort(function (TrackingEvent $a, TrackingEvent $b) {
                    return [$a->occurred_at, $a->id] <=> [$b->occurred_at, $b->id];
                })
                ->values()
                ->map(fn (TrackingEvent $e) => $this->serializeEvent($e, $locale))
                ->all(),
            'counts'   => [
                'international' => $events->where('kind', TrackingEventKind::INTERNATIONAL)->count(),
                'internal'      => $events->where('kind', TrackingEventKind::INTERNAL)->count(),
            ],
        ];
    }

    /** Container source_table corresponding to a shipment mode. */
    private function containerSourceTable(ShipmentMode $mode): string
    {
        return match ($mode) {
            ShipmentMode::SEA => 'containers_sea',
            ShipmentMode::SKY => 'containers_sky',
        };
    }

    /**
     * @return Collection<int, TrackingEvent>
     */
    private function loadEvents(ShipmentMode $mode, int $shipmentId, ?int $containerId, ?int $pieceId): Collection
    {
        $shipmentTable  = $mode->sourceTable();
        $containerTable = $this->containerSourceTable($mode);

        $query = TrackingEvent::query()
            ->customerVisible()
            ->where(function ($q) use ($shipmentTable, $shipmentId, $containerTable, $containerId) {
                $q->where(function ($qq) use ($shipmentTable, $shipmentId) {
                    $qq->where('shipment_source_table', $shipmentTable)
                       ->where('shipment_source_id', $shipmentId);
                });
                if ($containerId !== null) {
                    $q->orWhere(function ($qq) use ($containerTable, $containerId) {
                        $qq->where('shipment_source_table', $containerTable)
                           ->where('shipment_source_id', $containerId);
                    });
                }
            });

        if ($pieceId !== null) {
            // When viewing a specific piece, include events scoped to that
            // piece OR shipment-wide events (where shipment_piece_id is
            // null) — those apply to all pieces.
            $query->where(function ($q) use ($pieceId) {
                $q->whereNull('shipment_piece_id')
                  ->orWhere('shipment_piece_id', $pieceId);
            });
        }

        return $query->get();
    }

    /**
     * Customer-safe event payload. Excludes raw_payload (may contain
     * provider names + internal fields) and recorded_by_user_id (PII).
     */
    private function serializeEvent(TrackingEvent $e, ?string $locale): array
    {
        $message = null;
        if ($e->translation_key !== null) {
            $params = $e->translation_params ?? [];
            $message = $locale !== null
                ? trans($e->translation_key, $params, $locale)
                : trans($e->translation_key, $params);
        }

        return [
            'id'           => $e->id,
            'kind'         => $e->kind->value,
            'event_type'   => $e->event_type,
            'occurred_at'  => optional($e->occurred_at)?->toIso8601String(),
            'city'         => $e->city,
            'country'      => $e->country,
            'message'      => $message,
            'branch_id'    => $e->branch_id,
        ];
    }
}
