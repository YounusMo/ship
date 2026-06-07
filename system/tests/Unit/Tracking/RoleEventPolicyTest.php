<?php

declare(strict_types=1);

namespace Tests\Unit\Tracking;

use App\Modules\Tracking\Enums\BranchStaffRole;
use App\Modules\Tracking\Enums\InternalEventType;
use App\Modules\Tracking\Services\RoleEventPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers every cell of the 4×8 role × event matrix from docs/MANUAL.md
 * §21.2. If the matrix changes, update this test and the manual together.
 */
class RoleEventPolicyTest extends TestCase
{
    /**
     * @return array<string, array{0: BranchStaffRole, 1: InternalEventType, 2: bool}>
     */
    public static function matrixProvider(): array
    {
        // role => [event => expected]
        $m = [
            BranchStaffRole::MANAGER->value => [
                InternalEventType::RECEIVED_AT_HUB->value       => true,
                InternalEventType::IN_TRANSIT_INTERNAL->value   => true,
                InternalEventType::RECEIVED_AT_BRANCH->value    => true,
                InternalEventType::READY_FOR_PICKUP->value      => true,
                InternalEventType::DELIVERED_TO_CUSTOMER->value => true,
                InternalEventType::RETURNED_TO_HUB->value       => true,
                InternalEventType::LOST->value                  => true,
                InternalEventType::DAMAGED->value               => true,
            ],
            BranchStaffRole::RECEIVER->value => [
                InternalEventType::RECEIVED_AT_HUB->value       => true,
                InternalEventType::IN_TRANSIT_INTERNAL->value   => true,
                InternalEventType::RECEIVED_AT_BRANCH->value    => true,
                InternalEventType::READY_FOR_PICKUP->value      => true,
                InternalEventType::DELIVERED_TO_CUSTOMER->value => false,
                InternalEventType::RETURNED_TO_HUB->value       => true,
                InternalEventType::LOST->value                  => false,
                InternalEventType::DAMAGED->value               => true,
            ],
            BranchStaffRole::COURIER->value => [
                InternalEventType::RECEIVED_AT_HUB->value       => false,
                InternalEventType::IN_TRANSIT_INTERNAL->value   => false,
                InternalEventType::RECEIVED_AT_BRANCH->value    => false,
                InternalEventType::READY_FOR_PICKUP->value      => false,
                InternalEventType::DELIVERED_TO_CUSTOMER->value => true,
                InternalEventType::RETURNED_TO_HUB->value       => true,
                InternalEventType::LOST->value                  => false,
                InternalEventType::DAMAGED->value               => false,
            ],
            BranchStaffRole::AUDITOR->value => [
                InternalEventType::RECEIVED_AT_HUB->value       => false,
                InternalEventType::IN_TRANSIT_INTERNAL->value   => false,
                InternalEventType::RECEIVED_AT_BRANCH->value    => false,
                InternalEventType::READY_FOR_PICKUP->value      => false,
                InternalEventType::DELIVERED_TO_CUSTOMER->value => false,
                InternalEventType::RETURNED_TO_HUB->value       => false,
                InternalEventType::LOST->value                  => false,
                InternalEventType::DAMAGED->value               => false,
            ],
        ];

        $rows = [];
        foreach ($m as $roleStr => $events) {
            foreach ($events as $eventStr => $expected) {
                $name = sprintf(
                    '%s %s %s',
                    $roleStr,
                    $expected ? 'CAN' : 'CANNOT',
                    $eventStr,
                );
                $rows[$name] = [
                    BranchStaffRole::from($roleStr),
                    InternalEventType::from($eventStr),
                    $expected,
                ];
            }
        }
        return $rows;
    }

    #[DataProvider('matrixProvider')]
    public function test_matrix_cell(
        BranchStaffRole $role,
        InternalEventType $event,
        bool $expected,
    ): void {
        $policy = new RoleEventPolicy();
        $this->assertSame($expected, $policy->allows($role, $event));
    }

    public function test_allowed_events_for_manager_returns_all_eight(): void
    {
        $policy = new RoleEventPolicy();
        $events = $policy->allowedEventsForRole(BranchStaffRole::MANAGER);
        $this->assertCount(count(InternalEventType::cases()), $events);
    }

    public function test_allowed_events_for_auditor_is_empty(): void
    {
        $policy = new RoleEventPolicy();
        $this->assertSame([], $policy->allowedEventsForRole(BranchStaffRole::AUDITOR));
    }

    public function test_filter_with_no_role_returns_empty(): void
    {
        // Test the in-memory subset that doesn't hit the DB: when role
        // resolution returns null, the filter must return [].
        $policy = new class extends RoleEventPolicy {
            public function roleOnBranch(int $userId, int $branchId): ?BranchStaffRole
            {
                return null;
            }
        };

        $out = $policy->filterForUserOnBranch(
            [InternalEventType::RECEIVED_AT_HUB, InternalEventType::DAMAGED],
            42,
            7,
        );
        $this->assertSame([], $out);
    }

    public function test_filter_intersects_state_machine_with_role(): void
    {
        $policy = new class extends RoleEventPolicy {
            public function roleOnBranch(int $userId, int $branchId): ?BranchStaffRole
            {
                return BranchStaffRole::COURIER;
            }
        };

        // COURIER can do DELIVERED_TO_CUSTOMER + RETURNED_TO_HUB only.
        // Of these three candidates, only DELIVERED_TO_CUSTOMER and
        // RETURNED_TO_HUB survive.
        $out = $policy->filterForUserOnBranch(
            [
                InternalEventType::DELIVERED_TO_CUSTOMER,
                InternalEventType::RETURNED_TO_HUB,
                InternalEventType::RECEIVED_AT_BRANCH,
            ],
            42,
            7,
        );

        $values = array_map(fn ($e) => $e->value, $out);
        $this->assertContains('DELIVERED_TO_CUSTOMER', $values);
        $this->assertContains('RETURNED_TO_HUB', $values);
        $this->assertNotContains('RECEIVED_AT_BRANCH', $values);
    }
}
