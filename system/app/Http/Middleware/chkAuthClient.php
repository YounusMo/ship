<?php

namespace App\Http\Middleware;
use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class chkAuthClient
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // استثناء مسار logout
        if ($request->is('logout')) {
            return $next($request);
        }

        // منع دخول الـ web users
        if (Auth::guard('web')->check()) {
            return redirect('/login'); // أو أي صفحة أخرى بدل 404
        }

        // تحقق من أي guard
        if (! Auth::guard('web')->check() && ! Auth::guard('client')->check()) {
            return redirect('/login');
        }

        return $next($request);
    }
}
