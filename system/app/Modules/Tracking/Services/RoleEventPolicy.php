<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Tracking\Enums\BranchStaffRole;
use App\Modules\Tracking\Enums\InternalEventType;
use App\Modules\Tracking\Models\BranchStaff;

/**
 * Role-based authorization for internal scan events.
 *
 * The state machine (InternalStateMachine) answers "is this transition
 * legal given the shipment's current state?" This policy answers a
 * different question: "is this employee, in this role on this branch,
 * allowed to submit this kind of event at all?"
 *
 * Source of truth: docs/MANUAL.md §21.2. If you change the matrix
 * below, update the manual in the same PR.
 *
 * AUDITORs cannot submit any scan event — they read the queue only.
 *
 * @see docs/GAPS.md gap #2
 */
class RoleEventPolicy
{
    /**
     * The matrix. role.value => list<event_type.value>.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'MANAGER' => [
            'RECEIVED_AT_HUB',
            'IN_TRANSIT_INTERNAL',
            'RECEIVED_AT_BRANCH',
            'READY_FOR_PICKUP',
            'DELIVERED_TO_CUSTOMER',
            'RETURNED_TO_HUB',
            'LOST',
            'DAMAGED',
        ],
        'RECEIVER' => [
            'RECEIVED_AT_HUB',
            'IN_TRANSIT_INTERNAL',
            'RECEIVED_AT_BRANCH',
            'READY_FOR_PICKUP',
            'RETURNED_TO_HUB',
            'DAMAGED',
        ],
        'COURIER' => [
            'DELIVERED_TO_CUSTOMER',
            'RETURNED_TO_HUB',
        ],
        'AUDITOR' => [],
    ];

    public function allows(BranchStaffRole $role, InternalEventType $event): bool
    {
        return in_array($event->value, self::ALLOWED[$role->value] ?? [], true);
    }

    /**
     * @return list<InternalEventType>
     */
    public function allowedEventsForRole(BranchStaffRole $role): array
    {
        return array_values(array_filter(
            InternalEventType::cases(),
            fn (InternalEventType $e) => $this->allows($role, $e),
        ));
    }

    /**
     * Resolve the role a user holds on a specific branch.
     * Returns null if the user has no active assignment on that branch.
     */
    public function roleOnBranch(int $userId, int $branchId): ?BranchStaffRole
    {
        $row = BranchStaff::query()
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        return $row?->role;
    }

    /**
     * Filter a list of state-machine-allowed events down to those the
     * user is also authorized to submit on the given branch.
     *
     * If the user has no active role on the branch, returns [] —
     * conservative default that surfaces the misconfiguration in the
     * mobile UI rather than at scan time.
     *
     * @param list<InternalEventType> $candidates
     * @return list<InternalEventType>
     */
    public function filterForUserOnBranch(array $candidates, int $userId, int $branchId): array
    {
        $role = $this->roleOnBranch($userId, $branchId);
        if ($role === null) {
            return [];
        }

        return array_values(array_filter(
            $candidates,
            fn (InternalEventType $e) => $this->allows($role, $e),
        ));
    }
}
