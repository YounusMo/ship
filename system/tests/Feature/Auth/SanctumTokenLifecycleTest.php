<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Tracking\Enums\BranchRole;
use App\Modules\Tracking\Enums\BranchStaffRole;
use App\Modules\Tracking\Models\Branch;
use App\Modules\Tracking\Models\BranchStaff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers gap #4: Sanctum tokens now carry expires_at; clients and
 * employees can refresh and logout-all.
 *
 * @see docs/GAPS.md gap #4
 */
class SanctumTokenLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private string $password = 'CorrectHorse42!';

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
    }

    public function test_employee_login_sets_expires_at_around_seven_days(): void
    {
        [$user, $branch] = $this->employee(BranchStaffRole::MANAGER);

        $r = $this->postJson('/api/v1/employee/auth/login', [
            'email' => $user->email, 'password' => $this->password,
        ]);
        $r->assertStatus(200);
        $this->assertNotNull($r->json('expires_at'));

        // The DB row should reflect ~7 days from now.
        $row = DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)
            ->orderByDesc('id')->first();
        $this->assertNotNull($row->expires_at);
        $minutes = now()->diffInMinutes($row->expires_at);
        $this->assertGreaterThan(60 * 24 * 6, $minutes);
        $this->assertLessThan(60 * 24 * 8, $minutes);
    }

    public function test_employee_refresh_returns_new_token_and_revokes_old(): void
    {
        [$user, $branch] = $this->employee(BranchStaffRole::MANAGER);
        $login = $this->postJson('/api/v1/employee/auth/login', [
            'email' => $user->email, 'password' => $this->password,
        ]);
        $oldToken = $login->json('token');

        // Snapshot before refresh.
        $before = DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)->get(['id','name','expires_at']);
        $this->assertCount(1, $before);
        $oldRowId = (int) $before[0]->id;
        $oldExpectedTokenId = (int) explode('|', $oldToken)[0];
        $this->assertSame($oldRowId, $oldExpectedTokenId, 'Old token id should match the DB row id');

        $r = $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->postJson('/api/v1/employee/auth/refresh');
        $r->assertStatus(200);
        $newToken = $r->json('token');
        $this->assertNotEmpty($newToken);
        $this->assertNotSame($oldToken, $newToken);

        // After refresh: still 1 row (the new one); old one gone.
        $after = DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)->get(['id','name']);
        $this->assertCount(1, $after, 'Expected old token deleted, only new one remaining');
        $this->assertNotSame($oldRowId, (int) $after[0]->id, 'Remaining row should be the NEW one, not the old');

        // Old token is revoked — should 401. Clear cached auth state
        // from the previous request: Laravel's AuthManager caches the
        // resolved user across HTTP calls within a single test (in
        // production each request is a fresh process, so this is a
        // test-framework artifact, not a real concern).
        Auth::forgetGuards();
        $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->getJson('/api/v1/employee/me')
            ->assertStatus(401);

        Auth::forgetGuards();
        // New token works.
        $this->withHeader('Authorization', "Bearer {$newToken}")
            ->getJson('/api/v1/employee/me')
            ->assertStatus(200);

        // New token works.
        $this->withHeader('Authorization', "Bearer {$newToken}")
            ->getJson('/api/v1/employee/me')
            ->assertStatus(200);
    }

    public function test_employee_refresh_revokes_all_tokens_if_no_branches(): void
    {
        [$user, $branch] = $this->employee(BranchStaffRole::MANAGER);
        $login = $this->postJson('/api/v1/employee/auth/login', [
            'email' => $user->email, 'password' => $this->password,
        ]);
        $token = $login->json('token');

        // Simulate admin removing the user from every branch.
        BranchStaff::where('user_id', $user->id)->delete();

        $r = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/employee/auth/refresh');
        $r->assertStatus(403)->assertJsonPath('type', 'no_branch');

        // All tokens for the user have been wiped.
        $remaining = DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)->count();
        $this->assertSame(0, (int) $remaining);
    }

    public function test_employee_logout_all_revokes_every_token(): void
    {
        [$user, $branch] = $this->employee(BranchStaffRole::MANAGER);

        // Three logins → three tokens.
        $tokens = [];
        for ($i = 0; $i < 3; $i++) {
            $tokens[] = $this->postJson('/api/v1/employee/auth/login', [
                'email' => $user->email, 'password' => $this->password,
            ])->json('token');
        }
        $this->assertSame(3, (int) DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)->count());

        $r = $this->withHeader('Authorization', "Bearer {$tokens[0]}")
            ->postJson('/api/v1/employee/auth/logout-all');
        $r->assertStatus(200)->assertJsonPath('revoked', 3);

        $this->assertSame(0, (int) DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)->count());
    }

    public function test_purge_expired_command_drops_only_expired_tokens(): void
    {
        [$user, $branch] = $this->employee(BranchStaffRole::MANAGER);

        // Insert one expired token and one live one directly.
        DB::table('personal_access_tokens')->insert([
            [
                'tokenable_type' => User::class,
                'tokenable_id'   => $user->id,
                'name'           => 'expired',
                'token'          => hash('sha256', 'expired-fake'),
                'abilities'      => '["employee"]',
                'expires_at'     => now()->subDay(),
                'created_at'     => now()->subDays(8),
                'updated_at'     => now()->subDays(8),
            ],
            [
                'tokenable_type' => User::class,
                'tokenable_id'   => $user->id,
                'name'           => 'live',
                'token'          => hash('sha256', 'live-fake'),
                'abilities'      => '["employee"]',
                'expires_at'     => now()->addDay(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);

        $this->artisan('tokens:purge-expired')
            ->expectsOutputToContain('Deleted 1 expired token')
            ->assertSuccessful();

        $names = DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)
            ->pluck('name')->all();
        $this->assertContains('live', $names);
        $this->assertNotContains('expired', $names);
    }

    /**
     * @return array{0: User, 1: Branch}
     */
    private function employee(BranchStaffRole $role): array
    {
        $uniq = uniqid();
        $user = new User([
            'name'     => "Emp {$uniq}",
            'email'    => "emp-{$uniq}@example.test",
            'password' => Hash::make($this->password),
        ]);
        $user->save();

        $branch = Branch::create([
            'code' => "TKN-{$uniq}", 'name' => 'Hub', 'role' => BranchRole::HUB,
            'country' => 'LY', 'city' => 'Tripoli',
        ]);

        BranchStaff::create([
            'branch_id' => $branch->id,
            'user_id'   => $user->id,
            'role'      => $role,
            'is_active' => true,
        ]);

        return [$user, $branch];
    }
}
