<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mirrors EnsureClient — narrows Sanctum-authenticated requests on the
 * /api/v1/employee/* surface to User tokenables that carry an "employee"
 * ability. Blocks both customer tokens and any future role-less staff
 * tokens that might exist.
 */
class EnsureEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'type'    => 'forbidden',
                'message' => 'Employee endpoint requires a staff token.',
            ], 403);
        }

        // The login endpoint stamps every employee token with the
        // 'employee' ability; tokens missing it are non-employee.
        if ($request->user()->currentAccessToken() && ! $request->user()->tokenCan('employee')) {
            return response()->json([
                'type'    => 'forbidden',
                'message' => 'Token lacks the employee ability.',
            ], 403);
        }

        return $next($request);
    }
}
