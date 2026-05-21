<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Models\User;
use App\Modules\Tracking\Enums\BranchRole;
use App\Modules\Tracking\Enums\BranchStaffRole;
use App\Modules\Tracking\Enums\InternalEventType;
use App\Modules\Tracking\Models\Branch;
use App\Modules\Tracking\Models\BranchStaff;
use App\Modules\Tracking\Models\Sticker;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Services\Stickers\StickerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exercises the Phase 5b employee surface end-to-end through real HTTP
 * routes: login → me → resolve → submit, plus branch-scope enforcement.
 */
class EmployeeApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private string $userPassword = 'CorrectHorse42!';
    private Branch $hub;
    private Branch $spoke;
    private int $pieceId;

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

        // Synthetic employee.
        $uniq = uniqid();
        $this->user = new User([
            'name'     => "Emp Test {$uniq}",
            'email'    => "emp-{$uniq}@example.test",
            'password' => Hash::make($this->userPassword),
        ]);
        $this->user->save();

        $this->hub = Branch::create([
            'code' => "TST-HUB-{$uniq}", 'name' => 'Hub', 'role' => BranchRole::HUB,
            'country' => 'LY', 'city' => 'Tripoli',
        ]);
        $this->spoke = Branch::create([
            'code' => "TST-SPK-{$uniq}", 'name' => 'Spoke', 'role' => BranchRole::SPOKE,
            'country' => 'LY', 'city' => 'Sirte',
        ]);

        // Only assign to hub — spoke is for the "unauthorized branch" test.
        BranchStaff::create([
            'branch_id' => $this->hub->id,
            'user_id'   => $this->user->id,
            'role'      => BranchStaffRole::RECEIVER,
            'is_active' => true,
        ]);

        // A synthetic shipment piece for scan submits.
        $this->pieceId = (int) DB::table('shipment_pieces')->insertGetId([
            'tracking_code' => 'EMP-TST-' . $uniq,
            'source_table'  => 'store_out_sea',
            'source_id'     => random_int(900_000_000, 999_999_999),
            'client_id'     => 1,
            'piece_index'   => 1,
            'piece_total'   => 1,
            'status'        => 'active',
            'created_by'    => $this->user->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_login_rejects_bad_password(): void
    {
        $r = $this->postJson('/api/v1/employee/auth/login', [
            'email'    => $this->user->email,
            'password' => 'nope',
        ]);
        $r->assertStatus(401)->assertJsonPath('type', 'invalid_credentials');
    }

    public function test_login_rejects_user_without_branch(): void
    {
        $orphanUser = new User([
            'name'     => 'No Branch ' . uniqid(),
            'email'    => 'orphan-' . uniqid() . '@example.test',
            'password' => Hash::make('xxxx'),
        ]);
        $orphanUser->save();

        $r = $this->postJson('/api/v1/employee/auth/login', [
            'email'    => $orphanUser->email,
            'password' => 'xxxx',
        ]);
        $r->assertStatus(403)->assertJsonPath('type', 'no_branch');
    }

    public function test_login_issues_token_with_employee_and_branch_abilities(): void
    {
        $r = $this->postJson('/api/v1/employee/auth/login', [
            'email'    => $this->user->email,
            'password' => $this->userPassword,
        ]);
        $r->assertStatus(200);
        $r->assertJsonPath('type', 'success');
        $this->assertNotEmpty($r->json('token'));

        $abilities = $r->json('abilities');
        $this->assertContains('employee', $abilities);
        $this->assertContains('branch:' . $this->hub->id, $abilities);
        $this->assertNotContains('branch:' . $this->spoke->id, $abilities);
    }

    public function test_me_returns_user_and_active_branches(): void
    {
        $token = $this->loginAndGetToken();

        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/employee/me');
        $r->assertStatus(200);
        $r->assertJsonPath('user.id', $this->user->id);
        $r->assertJsonCount(1, 'branches');
        $r->assertJsonPath('branches.0.branch.id', $this->hub->id);
        $r->assertJsonPath('branches.0.role', 'RECEIVER');
    }

    public function test_scan_resolve_unassigned_returns_received_at_hub_only(): void
    {
        $token = $this->loginAndGetToken();
        $sticker = app(StickerService::class)->issueBatch(1, $this->user->id);
        $stickerId = Sticker::query()->where('batch_id', $sticker->id)->value('id');

        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/resolve', [
                'sticker_id' => $stickerId,
            ]);
        $r->assertStatus(200);
        $r->assertJsonPath('type', 'unassigned');
        $r->assertExactJson(array_merge($r->json(), [
            'allowed_event_types' => ['RECEIVED_AT_HUB'],
        ]));
    }

    public function test_scan_submit_first_scan_assigns_sticker_and_records_event(): void
    {
        $token = $this->loginAndGetToken();
        $batch = app(StickerService::class)->issueBatch(1, $this->user->id);
        $stickerId = Sticker::query()->where('batch_id', $batch->id)->value('id');

        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/submit', [
                'sticker_id'        => $stickerId,
                'event_type'        => 'RECEIVED_AT_HUB',
                'branch_id'         => $this->hub->id,
                'shipment_piece_id' => $this->pieceId,
                'notes'             => 'arrived at hub',
            ]);
        $r->assertStatus(201);
        $r->assertJsonPath('type', 'ok');

        $sticker = Sticker::query()->find($stickerId);
        $this->assertEquals($this->pieceId, $sticker->shipment_piece_id);
        $this->assertNotNull($sticker->assigned_at);

        $events = TrackingEvent::query()
            ->where('shipment_piece_id', $this->pieceId)
            ->get();
        $this->assertCount(1, $events);
        $this->assertEquals('RECEIVED_AT_HUB', $events->first()->event_type);
    }

    public function test_scan_submit_rejected_for_wrong_branch_via_token_scope(): void
    {
        $token = $this->loginAndGetToken();
        $batch = app(StickerService::class)->issueBatch(1, $this->user->id);
        $stickerId = Sticker::query()->where('batch_id', $batch->id)->value('id');

        // User is staff at hub, not spoke. Submitting with branch_id = spoke
        // must be rejected at the branch.scope middleware layer (403).
        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/submit', [
                'sticker_id'        => $stickerId,
                'event_type'        => 'RECEIVED_AT_HUB',
                'branch_id'         => $this->spoke->id,
                'shipment_piece_id' => $this->pieceId,
            ]);
        $r->assertStatus(403);
        $r->assertJsonPath('type', 'branch_scope_denied');
    }

    public function test_scan_submit_invalid_transition_returns_422(): void
    {
        $token = $this->loginAndGetToken();
        $batch = app(StickerService::class)->issueBatch(1, $this->user->id);
        $stickerId = Sticker::query()->where('batch_id', $batch->id)->value('id');

        // Skip the hub-receive entirely — first scan as DELIVERED is illegal.
        // The unassigned-first-scan guard fires before the state machine,
        // so this should return type=unassigned_first_scan with 422.
        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/scan/submit', [
                'sticker_id'        => $stickerId,
                'event_type'        => 'DELIVERED_TO_CUSTOMER',
                'branch_id'         => $this->hub->id,
                'shipment_piece_id' => $this->pieceId,
            ]);
        $r->assertStatus(422);
        $r->assertJsonPath('type', 'unassigned_first_scan');
    }

    public function test_customer_token_rejected_from_employee_api(): void
    {
        // Create a synthetic client token + try to call /me.
        $clientId = (int) DB::table('clients')->insertGetId([
            'name'    => 'reject-test ' . uniqid(),
            'deleted' => '0',
        ]);
        $client = \App\Models\Client::query()->find($clientId);
        $clientToken = $client->createToken('mobile', ['client'])->plainTextToken;

        $r = $this->withHeader('Authorization', "Bearer {$clientToken}")
            ->getJson('/api/v1/employee/me');
        $r->assertStatus(403)->assertJsonPath('type', 'forbidden');
    }

    private function loginAndGetToken(): string
    {
        $r = $this->postJson('/api/v1/employee/auth/login', [
            'email'    => $this->user->email,
            'password' => $this->userPassword,
        ]);
        $r->assertStatus(200);
        return (string) $r->json('token');
    }
}
