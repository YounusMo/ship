<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

/**
 * Three flows for 2FA:
 *
 *   1. Enrollment   — staff opts in. Generates a secret, shows the QR,
 *                     verifies a code, then sets confirmed_at.
 *   2. Challenge    — at login: if the user has a confirmed secret,
 *                     they get redirected here to submit a code before
 *                     proceeding into the admin.
 *   3. Reset (admin)— an admin clears another user's 2FA from /users.
 *
 * @see docs/GAPS.md gap #6
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuthService $service)
    {
    }

    // ---------- Enrollment ----------

    public function showEnrollment(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect('/login');
        }
        if ($this->service->isEnrolled($user)) {
            return redirect('/')->with('status', '2FA is already enabled.');
        }

        // Generate a secret if one wasn't already prepared. Storing it
        // pre-confirmation is fine — without confirmed_at the rest of
        // the system still treats the user as un-enrolled.
        if ($user->two_factor_secret === null) {
            $user->forceFill(['two_factor_secret' => $this->service->generateSecret()])->save();
        }

        return view('pages.auth.2fa-enroll', [
            'qr_svg' => $this->service->qrCodeSvg($user, $user->two_factor_secret),
            'secret' => $user->two_factor_secret,
        ]);
    }

    public function confirmEnrollment(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = $request->user();
        if (! $user || $user->two_factor_secret === null) {
            return redirect()->route('two-factor.enroll');
        }

        if (! $this->service->verify($user->two_factor_secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Code did not verify. Try again.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        return redirect('/')->with('status', 'Two-factor authentication enabled.');
    }

    // ---------- Login challenge ----------

    public function showChallenge(Request $request): View|RedirectResponse
    {
        $pendingId = Session::get('2fa.user_id');
        if ($pendingId === null) {
            return redirect('/login');
        }
        return view('pages.auth.2fa-challenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $pendingId = Session::get('2fa.user_id');
        $user = $pendingId ? User::query()->find((int) $pendingId) : null;
        if (! $user || ! $this->service->isEnrolled($user)) {
            return redirect('/login')->withErrors(['email' => 'Session expired. Sign in again.']);
        }

        if (! $this->service->verify($user->two_factor_secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Code did not verify.']);
        }

        // Promote the pending session to a real authenticated one.
        Session::forget('2fa.user_id');
        Auth::login($user, (bool) Session::pull('2fa.remember', false));
        Session::regenerate();

        return redirect()->intended('/');
    }

    // ---------- Admin reset ----------

    /**
     * Admin-only — clear someone else's 2FA enrollment so they can
     * re-enroll on a new device. The route is gated by `type:admin`.
     */
    public function adminReset(Request $request, int $userId): RedirectResponse
    {
        $target = User::query()->find($userId);
        if (! $target) {
            return back()->withErrors(['user' => 'User not found.']);
        }
        $target->forceFill([
            'two_factor_secret'        => null,
            'two_factor_confirmed_at'  => null,
        ])->save();
        return back()->with('status', "2FA cleared for {$target->email}.");
    }
}
