<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * End-to-end flow: request reset → broker sends notification → submit
 * new password → broker verifies token → password updated, tokens
 * revoked.
 *
 * @see docs/GAPS.md gap #7
 */
class PasswordResetTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_request_form_renders(): void
    {
        $this->get('/password/request')
            ->assertStatus(200)
            ->assertSee('Reset password');
    }

    public function test_sending_link_for_unknown_email_shows_neutral_message(): void
    {
        Notification::fake();

        $this->post('/password/email', ['email' => 'nobody-' . uniqid() . '@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_full_reset_flow(): void
    {
        Notification::fake();
        $uniq = uniqid();
        $user = new User([
            'name'     => "Pwd Test {$uniq}",
            'email'    => "pwd-{$uniq}@example.test",
            'password' => Hash::make('OldPass1!'),
        ]);
        $user->save();

        // Step 1: request reset link.
        $this->post('/password/email', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        // Step 2: assert the notification was queued, capture the token.
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;
            return true;
        });
        $this->assertNotEmpty($token);

        // Step 3: GET reset form renders.
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertStatus(200)
            ->assertSee('Choose a new password');

        // Step 4: submit new password.
        $r = $this->post('/password/reset', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ]);
        $r->assertRedirect('/login');
        $r->assertSessionHas('status');

        // Step 5: new password works; old does not.
        $user->refresh();
        $this->assertTrue(Hash::check('NewPass1!', $user->password));
        $this->assertFalse(Hash::check('OldPass1!', $user->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $uniq = uniqid();
        $user = new User([
            'name'     => "Pwd Test {$uniq}",
            'email'    => "pwd-{$uniq}@example.test",
            'password' => Hash::make('OldPass1!'),
        ]);
        $user->save();

        $r = $this->post('/password/reset', [
            'token'                 => 'fake-token',
            'email'                 => $user->email,
            'password'              => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ]);
        $r->assertSessionHasErrors('email');
        $user->refresh();
        $this->assertTrue(Hash::check('OldPass1!', $user->password));
    }
}
