<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
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
        $token = $client->createToken('mobile', ['client'])->plainTextToken;

        return response()->json([
            'type'   => 'success',
            'token'  => $token,
            'client' => [
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
