<?php

namespace App\Http\Middleware;
use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class chkAuthAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    /**
     * Roles allowed to reach the admin section. A logged-in `web` user whose
     * `type` is not in this list is treated as unauthenticated.
     */
    private const ADMIN_ROLES = ['admin', 'branch_admin'];

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('client')->check()) {
            return redirect('/client');
        }

        if (! Auth::guard('web')->check()) {
            return redirect('/login');
        }

        if (! in_array(Auth::guard('web')->user()->type, self::ADMIN_ROLES, true)) {
            Auth::guard('web')->logout();
            return redirect('/login');
        }

        return $next($request);
    }
}
