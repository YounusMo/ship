<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow the request only if the authenticated user's `type` is in the
 * given list. Apply on top of `chkAuthAdmin` so authentication has
 * already happened.
 *
 * Usage:
 *
 *   Route::middleware(['chkAuthAdmin', 'type:admin'])->group(...);
 *   Route::middleware(['chkAuthAdmin', 'type:admin,branch_admin'])->group(...);
 *
 * Known types in this codebase:
 *   - `admin`        — full system access
 *   - `branch_admin` — restricted to their own branch
 *
 * Several controllers also check `->type` inline. The middleware is an
 * additional outer layer — defense in depth — and the canonical place
 * to declare "this route group requires admin" on new endpoints.
 *
 * @see docs/GAPS.md gap #3
 */
class RequireType
{
    public function handle(Request $request, Closure $next, string ...$allowedTypes): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->deny($request, 401, 'unauthenticated', $allowedTypes);
        }

        $userType = (string) ($user->type ?? '');
        if (! in_array($userType, $allowedTypes, true)) {
            return $this->deny($request, 403, 'forbidden', $allowedTypes, $userType);
        }

        return $next($request);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function deny(Request $request, int $status, string $kind, array $allowed, string $userType = ''): Response
    {
        $payload = [
            'type'    => $kind,
            'message' => $status === 401
                ? 'Authentication required.'
                : 'Your user type does not permit this action.',
            'required_any' => $allowed,
        ];
        if ($userType !== '') {
            $payload['actor_type'] = $userType;
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($payload, $status);
        }
        return response('Forbidden: required user type is ' . implode(' or ', $allowed), $status);
    }
}
