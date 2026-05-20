<?php

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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'chkAuthAdmin' => \App\Http\Middleware\chkAuthAdmin::class,
            'chkAuthClient' => \App\Http\Middleware\chkAuthClient::class,
            // EnsureClient narrows Sanctum-authenticated requests to the
            // Client model only, blocking staff personal-access-tokens
            // from hitting client-facing /api/* endpoints.
            'client.sanctum' => \App\Http\Middleware\EnsureClient::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
