<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tracking\Services\BranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Employee mobile-app login.
 *
 * Issues a Sanctum personal access token whose abilities include
 * 'employee' plus 'branch:N' for every active branch_staff row. The
 * mobile app stores the token in secure storage and uses it as Bearer
 * for every subsequent call. EnforceBranchScope middleware reads the
 * abilities to gate per-branch actions.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly BranchService $branchService,
    ) {
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|max:191',
            'password' => 'required|string|min:1|max:200',
            'device'   => 'nullable|string|max:200',
        ]);

        $email = strtolower(trim((string) $request->input('email')));
        $key = 'employee-login:' . $email . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'type'          => 'rate_limited',
                'message'       => 'Too many attempts. Try again shortly.',
                'retry_after_s' => RateLimiter::availableIn($key),
            ], 429);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'type'    => 'invalid_credentials',
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $branchAbilities = $this->branchService->abilitiesForUser($user->id);
        if ($branchAbilities === []) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'type'    => 'no_branch',
                'message' => 'User has no active branch_staff assignment.',
            ], 403);
        }

        RateLimiter::clear($key);

        $abilities = array_merge(['employee'], $branchAbilities);
        $deviceName = (string) ($request->input('device') ?? 'employee-app');
        $token = $user->createToken($deviceName, $abilities)->plainTextToken;

        return response()->json([
            'type'  => 'success',
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'abilities' => $abilities,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['type' => 'ok']);
    }
}
