<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the /api/* routes that must be hit by a Client (mobile app),
 * not a staff User. Sanctum's auth:sanctum middleware resolves the
 * token's tokenable, which could be either model — we narrow here.
 *
 * Without this, a staff personal-access-token could pull any client's
 * data via the mobile API.
 */
class EnsureClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user instanceof Client) {
            return response()->json([
                'type'    => 'forbidden',
                'message' => 'This endpoint is for client tokens only.',
            ], 403);
        }
        return $next($request);
    }
}
