<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Per-identifier login throttle. The default `throttle:5,1` middleware
        // keys on client IP, so attackers behind residential proxies bypass it
        // by rotating addresses. Keying on the submitted email (when present)
        // AND the IP forces both axes to be exhausted independently — an
        // attacker attempting one account from N IPs is throttled after 5
        // attempts per account, regardless of source.
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));
            return [
                Limit::perMinute(5)->by($email ?: $request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
    }
}
