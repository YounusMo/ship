<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Tracking\Enums\CustodyEventType;
use App\Modules\Tracking\Enums\InternalEventType;
use App\Modules\Tracking\Enums\TrackingEventKind;
use App\Modules\Tracking\Exceptions\InvalidScanTransitionException;
use App\Modules\Tracking\Models\CustodyEvent;
use App\Modules\Tracking\Models\EmployeeActionLog;
use App\Modules\Tracking\Models\TrackingEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records an employee scan as one atomic unit:
 *   1) State-machine guard — is this transition legal for the current
 *      event_type of this shipment?
 *   2) Branch scope guard — is the scanner physically at the branch
 *      that currently holds the shipment? (skipped for HUB-entry events)
 *   3) Insert tracking_event (kind=INTERNAL) — this is what the customer
 *      will see.
 *   4) Insert custody_event when the scan represents a hand-off
 *      (HUB→TRANSIT→BRANCH→DELIVERED).
 *   5) Insert employee_action_log audit row.
 *
 * Everything in one DB transaction. Idempotency-Key dedup is the
 * controller's responsibility (the unique index on
 * tracking_events.client_event_id is the safety net).
 */
class InternalTrackingService
{
    public function __construct(
        private readonly InternalStateMachine $stateMachine,
        private readonly BranchService $branchService,
    ) {
    }

    /**
     * @param  array{
     *   shipment_source_table: string,
     *   shipment_source_id:    int,
     *   shipment_piece_id?:    int|null,
     *   event_type:            InternalEventType,
     *   branch_id:             int,
     *   recorded_by_user_id:   int,
     *   occurred_at?:          \DateTimeInterface|string|null,
     *   notes?:                string|null,
     *   photos?:               array<int, string>|null,
     *   client_event_id?:      string|null,
     *   to_branch_id?:         int|null,
     *   ip_address?:           string|null,
     *   user_agent?:           string|null,
     *   translation_key?:      string|null,
     *   translation_params?:   array<string, mixed>|null,
     * }  $input
     */
    public function recordScan(array $input): TrackingEvent
    {
        $sourceTable = $input['shipment_source_table'];
        $sourceId    = (int) $input['shipment_source_id'];
        $pieceId     = $input['shipment_piece_id'] ?? null;
        $eventType   = $input['event_type'];
        $branchId    = (int) $input['branch_id'];
        $userId      = (int) $input['recorded_by_user_id'];
        $occurredAt  = $this->parseOccurredAt($input['occurred_at'] ?? null);

        if (! $eventType instanceof InternalEventType) {
            throw new RuntimeException('event_type must be an InternalEventType enum');
        }

        // 1. State-machine guard.
        $current = $this->latestInternalEventType($sourceTable, $sourceId, $pieceId);
        $this->stateMachine->assertTransition($current, $eventType);

        // 2. Branch scope guard (skip for hub-entry events that have no prior custody).
        $custody = $this->branchService->currentCustody($sourceTable, $sourceId, $pieceId);
        $skipScopeCheck = in_array($eventType, [
            InternalEventType::RECEIVED_AT_HUB,
            InternalEventType::RETURNED_TO_HUB,
            InternalEventType::LOST,
            InternalEventType::DAMAGED,
        ], true);
        if ($custody !== null && ! $skipScopeCheck) {
            $expectedBranch = (int) $custody->to_branch_id;
            if ($expectedBranch !== 0 && $expectedBranch !== $branchId) {
                throw InvalidScanTransitionException::branchScopeMismatch($branchId, $expectedBranch);
            }
        }

        return DB::transaction(function () use (
            $input, $sourceTable, $sourceId, $pieceId,
            $eventType, $branchId, $userId, $occurredAt, $custody,
        ) {
            $tracking = TrackingEvent::create([
                'shipment_source_table' => $sourceTable,
                'shipment_source_id'    => $sourceId,
                'shipment_piece_id'     => $pieceId,
                'kind'                  => TrackingEventKind::INTERNAL,
                'event_type'            => $eventType->value,
                'occurred_at'           => $occurredAt,
                'city'                  => null,
                'country'               => 'LY',
                'branch_id'             => $branchId,
                'raw_payload'           => [
                    'notes'  => $input['notes']  ?? null,
                    'photos' => $input['photos'] ?? null,
                ],
                'translation_key'       => $input['translation_key']    ?? "tracking::events.{$eventType->value}",
                'translation_params'    => $input['translation_params'] ?? null,
                'recorded_by_user_id'   => $userId,
                'client_event_id'       => $input['client_event_id']    ?? null,
                'is_customer_visible'   => true,
            ]);

            $custodyType = $this->custodyEventTypeFor($eventType);
            if ($custodyType !== null) {
                CustodyEvent::create([
                    'shipment_source_table' => $sourceTable,
                    'shipment_source_id'    => $sourceId,
                    'shipment_piece_id'     => $pieceId,
                    'event_type'            => $custodyType,
                    'from_branch_id'        => $custody?->to_branch_id,
                    'to_branch_id'          => $input['to_branch_id'] ?? $branchId,
                    'recorded_by_user_id'   => $userId,
                    'occurred_at'           => $occurredAt,
                    'photos'                => $input['photos'] ?? null,
                    'notes'                 => $input['notes']  ?? null,
                    'tracking_event_id'     => $tracking->id,
                ]);
            }

            EmployeeActionLog::create([
                'user_id'     => $userId,
                'branch_id'   => $branchId,
                'action'      => 'scan.' . strtolower($eventType->value),
                'entity_type' => $sourceTable,
                'entity_id'   => (string) $sourceId,
                'payload'     => [
                    'piece_id'          => $pieceId,
                    'event_type'        => $eventType->value,
                    'tracking_event_id' => $tracking->id,
                ],
                'ip_address'  => $input['ip_address'] ?? null,
                'user_agent'  => $input['user_agent'] ?? null,
                'created_at'  => $occurredAt,
            ]);

            return $tracking;
        });
    }

    private function latestInternalEventType(string $sourceTable, int $sourceId, ?int $pieceId): ?string
    {
        $q = TrackingEvent::query()
            ->forShipment($sourceTable, $sourceId)
            ->where('kind', TrackingEventKind::INTERNAL)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($pieceId !== null) {
            $q->where(function ($qq) use ($pieceId) {
                $qq->whereNull('shipment_piece_id')->orWhere('shipment_piece_id', $pieceId);
            });
        }

        return $q->value('event_type');
    }

    private function custodyEventTypeFor(InternalEventType $type): ?string
    {
        return match ($type) {
            InternalEventType::RECEIVED_AT_HUB       => CustodyEventType::RECEIVED_AT_HUB->value,
            InternalEventType::IN_TRANSIT_INTERNAL   => CustodyEventType::DISPATCHED->value,
            InternalEventType::RECEIVED_AT_BRANCH    => CustodyEventType::RECEIVED_AT_BRANCH->value,
            InternalEventType::READY_FOR_PICKUP      => CustodyEventType::READY_FOR_PICKUP->value,
            InternalEventType::DELIVERED_TO_CUSTOMER => CustodyEventType::DELIVERED_TO_CUSTOMER->value,
            InternalEventType::RETURNED_TO_HUB       => CustodyEventType::RETURNED_TO_HUB->value,
            InternalEventType::LOST                  => CustodyEventType::LOST->value,
            InternalEventType::DAMAGED               => CustodyEventType::DAMAGED->value,
        };
    }

    private function parseOccurredAt(mixed $input): Carbon
    {
        if ($input instanceof \DateTimeInterface) {
            return Carbon::instance($input);
        }
        if (is_string($input) && $input !== '') {
            return Carbon::parse($input);
        }
        return Carbon::now();
    }
}
