<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Tracking\Enums\InternalEventType;
use App\Modules\Tracking\Exceptions\InvalidScanTransitionException;

/**
 * Validates internal-event transitions before InternalTrackingService
 * writes them. The legal transitions mirror the operational lifecycle
 * inside Libya:
 *
 *   (nothing)               → RECEIVED_AT_HUB
 *   RECEIVED_AT_HUB         → IN_TRANSIT_INTERNAL | RETURNED_TO_HUB
 *                                                | LOST | DAMAGED
 *   IN_TRANSIT_INTERNAL     → RECEIVED_AT_BRANCH | LOST | DAMAGED
 *                                                | RETURNED_TO_HUB
 *   RECEIVED_AT_BRANCH      → READY_FOR_PICKUP | RETURNED_TO_HUB
 *                                              | DAMAGED
 *   READY_FOR_PICKUP        → DELIVERED_TO_CUSTOMER | RETURNED_TO_HUB
 *   DELIVERED_TO_CUSTOMER   → (terminal)
 *   RETURNED_TO_HUB         → RECEIVED_AT_HUB (re-enter the flow)
 *   LOST                    → (terminal)
 *   DAMAGED                 → READY_FOR_PICKUP | RETURNED_TO_HUB
 *
 * The state machine is stateless: callers pass the current event_type
 * (or null for a fresh shipment) and the proposed next event.
 */
class InternalStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        // Fresh shipment — nothing scanned yet.
        '' => [
            'RECEIVED_AT_HUB',
        ],
        'RECEIVED_AT_HUB' => [
            'IN_TRANSIT_INTERNAL',
            'RETURNED_TO_HUB',
            'LOST',
            'DAMAGED',
        ],
        'IN_TRANSIT_INTERNAL' => [
            'RECEIVED_AT_BRANCH',
            'LOST',
            'DAMAGED',
            'RETURNED_TO_HUB',
        ],
        'RECEIVED_AT_BRANCH' => [
            'READY_FOR_PICKUP',
            'RETURNED_TO_HUB',
            'DAMAGED',
        ],
        'READY_FOR_PICKUP' => [
            'DELIVERED_TO_CUSTOMER',
            'RETURNED_TO_HUB',
        ],
        'DELIVERED_TO_CUSTOMER' => [],
        'RETURNED_TO_HUB' => [
            'RECEIVED_AT_HUB',
        ],
        'LOST' => [],
        'DAMAGED' => [
            'READY_FOR_PICKUP',
            'RETURNED_TO_HUB',
        ],
    ];

    public function canTransition(?string $currentEventType, InternalEventType $proposed): bool
    {
        $current = $currentEventType ?? '';
        $allowed = self::TRANSITIONS[$current] ?? null;

        if ($allowed === null) {
            // Unknown current state — fail closed.
            return false;
        }

        return in_array($proposed->value, $allowed, true);
    }

    public function assertTransition(?string $currentEventType, InternalEventType $proposed): void
    {
        if (! $this->canTransition($currentEventType, $proposed)) {
            throw InvalidScanTransitionException::notAllowed($currentEventType, $proposed);
        }
    }

    /**
     * @return list<InternalEventType>
     */
    public function allowedNext(?string $currentEventType): array
    {
        $current = $currentEventType ?? '';
        $allowed = self::TRANSITIONS[$current] ?? [];
        return array_map(static fn (string $v) => InternalEventType::from($v), $allowed);
    }
}
