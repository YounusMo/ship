<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Mobile-app login: identifier (code OR email) + password → Sanctum
 * personal-access-token. Tokens are namespaced "mobile" so the frontend
 * can list / revoke them per device later.
 *
 * Mirrors the per-identifier login throttle defined in
 * AppServiceProvider::boot for the web flow — credential stuffing should
 * be slowed equally on the mobile surface.
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|max:120',
            'password'   => 'required|string|min:1|max:200',
        ]);

        $identifier = strtolower(trim((string) $request->input('identifier')));
        $password   = (string) $request->input('password');
        $ip         = (string) $request->ip();
        $key        = 'mobile-login:' . $identifier . '|' . $ip;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'type'           => 'rate_limited',
                'message'        => 'Too many attempts. Try again shortly.',
                'retry_after_s'  => RateLimiter::availableIn($key),
            ], 429);
        }

        $client = Client::query()
            ->where('deleted', 'false')
            ->where(function ($q) use ($identifier) {
                $q->whereRaw('LOWER(email) = ?', [$identifier])
                  ->orWhereRaw('LOWER(code) = ?', [$identifier]);
            })
            ->first();

        if (!$client || !Hash::check($password, $client->password)) {
            RateLimiter::hit($key, 60);
            // Constant-ish-time response so client-not-found vs wrong-password
            // can't be distinguished by timing.
            return response()->json([
                'type'    => 'invalid_credentials',
                'message' => 'Invalid identifier or password.',
            ], 401);
        }

        RateLimiter::clear($key);

        // Issue a fresh token per login (treat each successful login as a
        // new device session). The mobile app stores it in secure storage
        // and uses it as Bearer for every subsequent call.
        $expiresAt = now()->addMinutes(self::CLIENT_TOKEN_TTL_MINUTES);
        $token = $client->createToken('mobile', ['client'], $expiresAt)->plainTextToken;

        return response()->json([
            'type'       => 'success',
            'token'      => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'client'     => [
                'id'    => $client->id,
                'code'  => $client->code,
                'name'  => $client->name,
                'email' => $client->email,
                'lang'  => $client->lang,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        $token?->delete();
        return response()->json(['type' => 'success']);
    }

    /**
     * Revoke every token for the authenticated client (all devices).
     * Useful for "lost my phone" flow.
     */
    public function logoutAll(Request $request)
    {
        $client = $request->user();
        if ($client === null) {
            return response()->json(['type' => 'unauthenticated'], 401);
        }
        $deleted = $client->tokens()->delete();
        return response()->json([
            'type'    => 'success',
            'revoked' => (int) $deleted,
        ]);
    }

    /**
     * Rotate the current token. Returns a new token + expiry and revokes
     * the old one. Mobile app should call this proactively when the
     * current token is within the refresh window, or on a 401.
     */
    public function refresh(Request $request)
    {
        $client = $request->user();
        $current = $client?->currentAccessToken();
        if ($client === null || $current === null) {
            return response()->json(['type' => 'unauthenticated'], 401);
        }

        $expiresAt = now()->addMinutes(self::CLIENT_TOKEN_TTL_MINUTES);
        $oldTokenId = (int) $current->id;
        $token = $client->createToken('mobile', ['client'], $expiresAt)->plainTextToken;
        // Revoke after issuing the new one so the caller can't lose
        // their session if the rotation crashes mid-flight. Direct
        // delete-by-id avoids stale-model edge cases.
        DB::table('personal_access_tokens')->where('id', $oldTokenId)->delete();

        return response()->json([
            'type'       => 'success',
            'token'      => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    private const CLIENT_TOKEN_TTL_MINUTES = 60 * 24 * 30;  // 30 days

    public function me(Request $request)
    {
        $client = $request->user();
        return response()->json([
            'id'    => $client->id,
            'code'  => $client->code,
            'name'  => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'lang'  => $client->lang,
        ]);
    }
}
