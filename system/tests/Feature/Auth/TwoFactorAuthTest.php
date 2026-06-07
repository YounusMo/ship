<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

/**
 * Covers gap #6: TOTP enrollment, login challenge, admin reset.
 */
class TwoFactorAuthTest extends TestCase
{
    use DatabaseTransactions;

    private string $password = 'OldPass1!';

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

    public function test_service_generates_and_verifies_code(): void
    {
        $service = app(TwoFactorAuthService::class);
        $secret = $service->generateSecret();
        $this->assertNotEmpty($secret);

        $code = (new Google2FA())->getCurrentOtp($secret);
        $this->assertTrue($service->verify($secret, $code));
        $this->assertFalse($service->verify($secret, '000000'));
        $this->assertFalse($service->verify($secret, 'bad'));
    }

    public function test_enrollment_flow_sets_confirmed_at(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        // First load — generates secret, returns the QR page.
        $r = $this->get(route('two-factor.enroll'));
        $r->assertStatus(200);
        $r->assertSee('Enable two-factor');

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        // Confirm with the right code.
        $code = (new Google2FA())->getCurrentOtp($user->two_factor_secret);
        $r = $this->post(route('two-factor.enroll.confirm'), ['code' => $code]);
        $r->assertRedirect('/');
        $r->assertSessionHas('status');

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    public function test_login_redirects_to_challenge_when_enrolled(): void
    {
        $user = $this->makeUser();
        $secret = app(TwoFactorAuthService::class)->generateSecret();
        DB::table('users')->where('id', $user->id)->update([
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $r = $this->post('/auth/user/login', [
            'email'    => $user->email,
            'password' => $this->password,
        ]);
        $r->assertRedirect(route('two-factor.challenge'));
        $this->assertSame($user->id, session('2fa.user_id'));
        $this->assertGuest();
    }

    public function test_challenge_verifies_and_logs_in(): void
    {
        $user = $this->makeUser();
        $secret = app(TwoFactorAuthService::class)->generateSecret();
        DB::table('users')->where('id', $user->id)->update([
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        // Set up the pending session as the login flow would.
        session(['2fa.user_id' => $user->id]);
        $code = (new Google2FA())->getCurrentOtp($secret);

        $r = $this->post(route('two-factor.challenge.verify'), ['code' => $code]);
        $r->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('2fa.user_id'));
    }

    public function test_wrong_code_does_not_log_in(): void
    {
        $user = $this->makeUser();
        $secret = app(TwoFactorAuthService::class)->generateSecret();
        DB::table('users')->where('id', $user->id)->update([
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        session(['2fa.user_id' => $user->id]);
        $r = $this->post(route('two-factor.challenge.verify'), ['code' => '000000']);
        $r->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_admin_can_reset_another_users_2fa(): void
    {
        $admin  = $this->makeUser(['type' => 'admin']);
        $target = $this->makeUser(['type' => 'branch_admin']);
        DB::table('users')->where('id', $target->id)->update([
            'two_factor_secret'       => 'whatever',
            'two_factor_confirmed_at' => now(),
        ]);
        $target->refresh();
        $this->assertNotNull($target->two_factor_confirmed_at);

        $this->actingAs($admin);
        $r = $this->post(route('two-factor.admin.reset', ['id' => $target->id]));
        $r->assertRedirect();

        $target->refresh();
        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_confirmed_at);
    }

    private function makeUser(array $overrides = []): User
    {
        $uniq = uniqid();
        $user = new User(array_merge([
            'name'     => "2FA Test {$uniq}",
            'email'    => "tfa-{$uniq}@example.test",
            'password' => Hash::make($this->password),
            'type'     => 'admin',
            'code'     => 'TFA-' . $uniq,
        ], $overrides));
        $user->save();
        return $user;
    }
}
