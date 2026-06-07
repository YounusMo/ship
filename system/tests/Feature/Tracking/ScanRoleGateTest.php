<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Models\User;
use App\Modules\Tracking\Enums\BranchRole;
use App\Modules\Tracking\Enums\BranchStaffRole;
use App\Modules\Tracking\Models\Branch;
use App\Modules\Tracking\Models\BranchStaff;
use App\Modules\Tracking\Models\Sticker;
use App\Modules\Tracking\Services\Stickers\StickerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Integration tests for the role-event gate on the scan submit endpoint.
 * Proves the wiring between RoleEventPolicy and ScanController, not the
 * matrix itself (that's covered by RoleEventPolicyTest).
 *
 * @see docs/GAPS.md gap #2
 */
class ScanRoleGateTest extends TestCase
{
    use DatabaseTransactions;

    private string $password = 'CorrectHorse42!';
    private Branch $branch;
    private int $pieceId;
    private int $systemUserId;

    protected function connectionsToTransact(): array
    {
        return ['mysql'];
    }

    protected function refreshApplication(): void
    {
        $envDb = trim((string) shell_exec("grep '^DB_DATABASE=' .env | cut -d= -f2")) ?: 'ship_system';
        putenv("DB_DATABASE={$envDb}");
        putenv('DB_CONNECTION=mysql');
        $_ENV['DB_DATABASE']      = $envDb;
        $_ENV['DB_CONNECTION']    = 'mysql';
        $_SERVER['DB_DATABASE']   = $envDb;
        $_SERVER['DB_CONNECTION'] = 'mysql';

        parent::refreshApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL not available: ' . $e->getMessage());
        }

        $uniq = uniqid();

        // A system-level user just for issuing sticker batches (FK).
        $sys = new User([
            'name'     => "Sys {$uniq}",
            'email'    => "sys-{$uniq}@example.test",
            'password' => Hash::make($this->password),
        ]);
        $sys->save();
        $this->systemUserId = (int) $sys->id;

        $this->branch = Branch::create([
            'code' => "ROLE-TST-{$uniq}", 'name' => 'Role Hub', 'role' => BranchRole::HUB,
            'country' => 'LY', 'city' => 'Tripoli',
        ]);

        $this->pieceId = (int) DB::table('shipment_pieces')->insertGetId([
            'tracking_code' => "ROLE-TST-{$uniq}",
            'source_table'  => 'store_out_sea',
            'source_id'     => random_int(900_000_000, 999_999_999),
            'client_id'     => 1,
            'piece_index'   => 1,
            'piece_total'   => 1,
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_auditor_cannot_submit_any_event(): void
    {
        $token = $this->loginAs(BranchStaffRole::AUDITOR);
        $stickerId = $this->freshStickerId();

        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/submit', [
                'sticker_id'        => $stickerId,
                'event_type'        => 'RECEIVED_AT_HUB',
                'branch_id'         => $this->branch->id,
                'shipment_piece_id' => $this->pieceId,
            ]);

        $r->assertStatus(403)->assertJsonPath('type', 'role_action_denied');
    }

    public function test_courier_cannot_submit_received_at_hub(): void
    {
        $token = $this->loginAs(BranchStaffRole::COURIER);
        $stickerId = $this->freshStickerId();

        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/submit', [
                'sticker_id'        => $stickerId,
                'event_type'        => 'RECEIVED_AT_HUB',
                'branch_id'         => $this->branch->id,
                'shipment_piece_id' => $this->pieceId,
            ]);

        $r->assertStatus(403)->assertJsonPath('type', 'role_action_denied');
        $r->assertJsonPath('role', 'COURIER');
        $r->assertJsonPath('event_type', 'RECEIVED_AT_HUB');
    }

    public function test_receiver_cannot_submit_lost(): void
    {
        $token = $this->loginAs(BranchStaffRole::RECEIVER);
        $stickerId = $this->freshStickerId();

        // First, prime the sticker with a successful RECEIVED_AT_HUB so
        // we're not blocked by the unassigned-first-scan rule.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/submit', [
                'sticker_id'        => $stickerId,
                'event_type'        => 'RECEIVED_AT_HUB',
                'branch_id'         => $this->branch->id,
                'shipment_piece_id' => $this->pieceId,
            ])->assertStatus(201);

        // Now try LOST — should be denied to RECEIVER.
        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/submit', [
                'sticker_id' => $stickerId,
                'event_type' => 'LOST',
                'branch_id'  => $this->branch->id,
            ]);

        $r->assertStatus(403)->assertJsonPath('type', 'role_action_denied');
    }

    public function test_manager_can_submit_lost(): void
    {
        $token = $this->loginAs(BranchStaffRole::MANAGER);
        $stickerId = $this->freshStickerId();

        // Prime with RECEIVED_AT_HUB.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/submit', [
                'sticker_id'        => $stickerId,
                'event_type'        => 'RECEIVED_AT_HUB',
                'branch_id'         => $this->branch->id,
                'shipment_piece_id' => $this->pieceId,
            ])->assertStatus(201);

        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/submit', [
                'sticker_id' => $stickerId,
                'event_type' => 'LOST',
                'branch_id'  => $this->branch->id,
            ]);

        $r->assertStatus(201)->assertJsonPath('type', 'ok');
    }

    public function test_resolve_filters_allowed_events_when_branch_id_provided(): void
    {
        $token = $this->loginAs(BranchStaffRole::COURIER);
        $stickerId = $this->freshStickerId();

        // Without branch_id: backwards-compat. Returns state-machine list.
        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/resolve', [
                'sticker_id' => $stickerId,
            ]);
        $r->assertStatus(200)->assertJsonPath('type', 'unassigned');
        $this->assertContains('RECEIVED_AT_HUB', $r->json('allowed_event_types'));

        // With branch_id: courier can't do RECEIVED_AT_HUB → empty list.
        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/resolve', [
                'sticker_id' => $stickerId,
                'branch_id'  => $this->branch->id,
            ]);
        $r->assertStatus(200)->assertJsonPath('type', 'unassigned');
        $this->assertSame([], $r->json('allowed_event_types'));
    }

    public function test_user_with_no_active_role_on_branch_is_denied(): void
    {
        // Create a user assigned to a *different* branch, then issue
        // a token with the right branch ability for *this* branch by
        // hand. (Simulates the scenario where branch_staff is removed
        // mid-session but the token still carries the ability.)
        $uniq = uniqid();
        $user = new User([
            'name'     => "Other {$uniq}",
            'email'    => "other-{$uniq}@example.test",
            'password' => Hash::make($this->password),
        ]);
        $user->save();

        $token = $user->createToken(
            'test',
            ['employee', 'branch:' . $this->branch->id],
        )->plainTextToken;

        $stickerId = $this->freshStickerId();

        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/submit', [
                'sticker_id'        => $stickerId,
                'event_type'        => 'RECEIVED_AT_HUB',
                'branch_id'         => $this->branch->id,
                'shipment_piece_id' => $this->pieceId,
            ]);

        $r->assertStatus(403)->assertJsonPath('type', 'no_active_role');
    }

    private function loginAs(BranchStaffRole $role): string
    {
        $uniq = uniqid();
        $user = new User([
            'name'     => "{$role->value} {$uniq}",
            'email'    => strtolower($role->value) . "-{$uniq}@example.test",
            'password' => Hash::make($this->password),
        ]);
        $user->save();

        BranchStaff::create([
            'branch_id' => $this->branch->id,
            'user_id'   => $user->id,
            'role'      => $role,
            'is_active' => true,
        ]);

        $r = $this->postJson('/api/v1/employee/auth/login', [
            'email'    => $user->email,
            'password' => $this->password,
        ]);
        $r->assertStatus(200);
        return $r->json('token');
    }

    private function freshStickerId(): string
    {
        $batch = app(StickerService::class)->issueBatch(1, $this->systemUserId);
        return Sticker::query()->where('batch_id', $batch->id)->value('id');
    }
}
