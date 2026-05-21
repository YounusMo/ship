<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated employee's Sanctum token carries the
 * "branch:N" ability matching the branch being acted on.
 *
 * Branch id is resolved in order: route param {branch}, then JSON body
 * "branch_id", then query "branch_id". If none is provided, the
 * middleware is a no-op (some endpoints — /me, /activity — aren't
 * branch-scoped).
 */
class EnforceBranchScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $branchId = $request->route('branch')
            ?? $request->input('branch_id')
            ?? $request->query('branch_id');

        if ($branchId === null) {
            return $next($request);
        }

        $ability = 'branch:' . (int) $branchId;
        $user = $request->user();

        if (! $user || ! $user->currentAccessToken() || ! $user->tokenCan($ability)) {
            return response()->json([
                'type'      => 'branch_scope_denied',
                'message'   => 'Token is not scoped to this branch.',
                'branch_id' => (int) $branchId,
            ], 403);
        }

        return $next($request);
    }
}
