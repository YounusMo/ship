<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Self-serve password reset for staff (User model). Uses Laravel's
 * Password broker so the heavy lifting (token TTL, single-use, hashing)
 * is handled by the framework.
 *
 * Aggressive throttling is applied at the route layer via the custom
 * 'login' RateLimiter (per-identifier 5/min + per-IP 20/min). See
 * AppServiceProvider::boot for the limiter definition.
 *
 * @see docs/GAPS.md gap #7
 */
class PasswordResetController extends Controller
{
    public function showRequestForm(): View
    {
        return view('pages.auth.password-request');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email|max:191',
        ]);

        $status = Password::broker('users')->sendResetLink(
            $request->only('email'),
        );

        // Always show the same neutral message regardless of whether
        // the email exists — don't leak which addresses are valid staff.
        return back()->with(
            'status',
            'If that address belongs to a staff account, a reset link has been sent.',
        );
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('pages.auth.password-reset', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token'                 => 'required|string',
            'email'                 => 'required|email',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                // Revoke every Sanctum token on the user. If their
                // password was compromised, the bearer tokens were too.
                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }
                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect('/login')->with(
                'status',
                'Password updated. You can now sign in.',
            );
        }

        return back()->withErrors(['email' => trans($status)]);
    }
}
