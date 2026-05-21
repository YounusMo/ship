<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Tracking\Enums\InternalEventType;
use App\Modules\Tracking\Enums\TrackingEventKind;
use App\Modules\Tracking\Models\TrackingEvent;
use Illuminate\Support\Collection;

/**
 * Derives a shipment's current high-level status from its event stream.
 *
 * Status taxonomy (customer-facing, mode-agnostic):
 *   AT_ORIGIN              — created but not yet picked up
 *   IN_TRANSIT_INTL        — moving from origin to Libyan port
 *   AT_PORT                — discharged at Libyan port
 *   AT_HUB                 — received at ShipFlow hub branch
 *   IN_TRANSIT_INTERNAL    — moving between Libyan branches
 *   AT_DESTINATION         — at the spoke branch
 *   READY_FOR_PICKUP       — customer can collect
 *   DELIVERED              — handed to customer
 *   EXCEPTION              — lost, damaged, returned, refused
 *
 * Computation is pure on top of a chronological event list. No DB writes,
 * no auth; callers (UnifiedTimelineService, employee dashboards) supply
 * the events they want considered.
 */
class StatusComputer
{
    /**
     * Map known ShipsGo event codes to coarse status. Codes not in this
     * map don't transition status — they're still shown in the timeline.
     */
    private const INTERNATIONAL_STATUS_MAP = [
        'GATE_IN'    => 'AT_ORIGIN',
        'LOADED'     => 'IN_TRANSIT_INTL',
        'DEPARTED'   => 'IN_TRANSIT_INTL',
        'IN_TRANSIT' => 'IN_TRANSIT_INTL',
        'ARRIVED'    => 'AT_PORT',
        'DISCHARGED' => 'AT_PORT',
    ];

    private const INTERNAL_STATUS_MAP = [
        InternalEventType::RECEIVED_AT_HUB->value       => 'AT_HUB',
        InternalEventType::IN_TRANSIT_INTERNAL->value   => 'IN_TRANSIT_INTERNAL',
        InternalEventType::RECEIVED_AT_BRANCH->value    => 'AT_DESTINATION',
        InternalEventType::READY_FOR_PICKUP->value      => 'READY_FOR_PICKUP',
        InternalEventType::DELIVERED_TO_CUSTOMER->value => 'DELIVERED',
        InternalEventType::RETURNED_TO_HUB->value       => 'EXCEPTION',
        InternalEventType::LOST->value                  => 'EXCEPTION',
        InternalEventType::DAMAGED->value               => 'EXCEPTION',
    ];

    /**
     * @param  Collection<int, TrackingEvent>  $events
     */
    public function derive(Collection $events): string
    {
        if ($events->isEmpty()) {
            return 'AT_ORIGIN';
        }

        // Walk newest → oldest, return the first event that maps to a
        // status. Tiebreak on id so events recorded in the same instant
        // (common in test fixtures and rapid scans) have a stable order.
        $sorted = $events->sort(function ($a, $b) {
            return [$b->occurred_at, $b->id] <=> [$a->occurred_at, $a->id];
        })->values();

        foreach ($sorted as $event) {
            $status = $this->statusFor($event);
            if ($status !== null) {
                return $status;
            }
        }

        return 'AT_ORIGIN';
    }

    private function statusFor(TrackingEvent $event): ?string
    {
        return match ($event->kind) {
            TrackingEventKind::INTERNATIONAL =>
                self::INTERNATIONAL_STATUS_MAP[$event->event_type] ?? null,
            TrackingEventKind::INTERNAL =>
                self::INTERNAL_STATUS_MAP[$event->event_type] ?? null,
        };
    }
}
