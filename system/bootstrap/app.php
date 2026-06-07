<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // One cron entry on the host drives all of this:
        //   * * * * * cd /var/www/system && php artisan schedule:run
        //
        // See docs/GAPS.md gap #1. Per-command rationale:

        // Daily reminder pass for stale proformas. Default selection
        // criteria live inside SourcingRemindCommand (3 day age, 5 day
        // cooldown). 09:00 is during business hours so the recipient
        // sees the email when they're likely working.
        $schedule->command('sourcing:remind')
            ->dailyAt('09:00')
            ->onOneServer()
            ->withoutOverlapping();

        // Nightly deal-health snapshot for the sourcing dashboards.
        // 02:00 keeps it out of the way of the business day.
        $schedule->command('sourcing:health-snapshot')
            ->dailyAt('02:00')
            ->onOneServer()
            ->withoutOverlapping();

        // Periodic ShipsGo reconcile — catches containers whose webhook
        // payloads went missing or arrived out of order. Every four
        // hours bounds the worst-case staleness without hammering the
        // ShipsGo API.
        $schedule->command('tracking:reconcile-stuck')
            ->everyFourHours()
            ->onOneServer()
            ->withoutOverlapping();

        // Nightly purge of expired Sanctum tokens. Cheap and safe.
        $schedule->command('tokens:purge-expired')
            ->dailyAt('03:00')
            ->onOneServer()
            ->withoutOverlapping();

        // Data-retention housekeeping. See docs/GAPS.md gap #8.
        // All have --dry-run flags if you want to preview before running
        // ad-hoc, but here they run live on schedule.
        $schedule->command('purge:webhook-payloads')
            ->dailyAt('03:15')
            ->onOneServer()
            ->withoutOverlapping();

        $schedule->command('purge:failed-jobs')
            ->weeklyOn(1, '03:30')  // Mondays 03:30
            ->onOneServer()
            ->withoutOverlapping();

        $schedule->command('purge:read-notifications')
            ->weeklyOn(1, '03:45')  // Mondays 03:45
            ->onOneServer()
            ->withoutOverlapping();

        // Audit-log archive runs on the 1st of every month at 04:00.
        // The artifact goes to storage/app/audit-archive/; ops should
        // sync it to cold storage as part of nightly backups.
        $schedule->command('archive:audit-log')
            ->monthlyOn(1, '04:00')
            ->onOneServer()
            ->withoutOverlapping();

        // NOT scheduled, manual-only by design:
        //   stickers:generate           — admin runs before a print batch
        //   tracking:shipsgo-smoke      — diagnostic
        //   tracking:e2e-walk           — diagnostic
        //   journal:backfill            — one-shot migration helper
        //   shipments:pieces-backfill   — one-shot migration helper
        //   schema:reset                — dev-only
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust forwarded headers from the load balancer / CDN so that
        // $request->isSecure(), ->ip(), ->host() reflect the real client
        // when terminating TLS at the edge. '*' is appropriate because
        // origin port 80/443 should already be locked to Cloudflare IPs
        // at the host firewall.
        $middleware->trustProxies(at: '*');

        // Defensive HTTP headers on every response. See gap #19.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'chkAuthAdmin' => \App\Http\Middleware\chkAuthAdmin::class,
            'chkAuthClient' => \App\Http\Middleware\chkAuthClient::class,
            // Outer authorization gate by user.type. Always pair with
            // chkAuthAdmin. See app/Http/Middleware/RequireType.php and
            // docs/GAPS.md gap #3.
            'type' => \App\Http\Middleware\RequireType::class,
            // EnsureClient narrows Sanctum-authenticated requests to the
            // Client model only, blocking staff personal-access-tokens
            // from hitting client-facing /api/* endpoints.
            'client.sanctum' => \App\Http\Middleware\EnsureClient::class,
            // White-label sanitizer for mobile responses — throws in
            // local/testing, scrubs+logs in production. See ALIGNMENT_PATCH.md §2.8.
            'mobile.sanitize' => \App\Modules\Tracking\Http\Middleware\EnforceMobileSanitization::class,
            // Employee API gate — narrows Sanctum tokenable to User and
            // requires the 'employee' ability stamped at login.
            'employee.sanctum' => \App\Modules\Tracking\Http\Middleware\EnsureEmployee::class,
            // Per-route branch scope check ('branch:N' ability must match
            // the branch_id from the route/body/query).
            'branch.scope' => \App\Modules\Tracking\Http\Middleware\EnforceBranchScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Forward unhandled exceptions to Sentry when SENTRY_LARAVEL_DSN
        // is set. Without a DSN this is a no-op, so local dev and tests
        // are unaffected. See docs/GAPS.md gap #9.
        \Sentry\Laravel\Integration::handles($exceptions);
    })->create();
