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
     * @return array{
     *   status: string,
     *   timeline: list<array<string, mixed>>,
     *   counts: array{international: int, internal: int}
     * }
     */
    public function for(ShipmentMode $mode, int $shipmentId, ?int $pieceId = null, ?string $locale = null): array
    {
        $events = $this->loadEvents($mode, $shipmentId, $pieceId);

        return [
            'status'   => $this->statusComputer->derive($events),
            'timeline' => $events
                ->sortBy('occurred_at')
                ->values()
                ->map(fn (TrackingEvent $e) => $this->serializeEvent($e, $locale))
                ->all(),
            'counts'   => [
                'international' => $events->where('kind', TrackingEventKind::INTERNATIONAL)->count(),
                'internal'      => $events->where('kind', TrackingEventKind::INTERNAL)->count(),
            ],
        ];
    }

    /**
     * @return Collection<int, TrackingEvent>
     */
    private function loadEvents(ShipmentMode $mode, int $shipmentId, ?int $pieceId): Collection
    {
        $query = TrackingEvent::query()
            ->forShipment($mode->sourceTable(), $shipmentId)
            ->customerVisible();

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
