<?php
// bootstrap/app.php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API middleware group with Sanctum
        $middleware->group('api', [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Rate limiting for streaming endpoints
        $middleware->alias([
            'signed.stream' => \App\Http\Middleware\SignedStreamUrl::class,
            'concurrency.limit' => \App\Http\Middleware\ConcurrentStreamLimit::class,
            'rate.limit' => \App\Http\Middleware\RateLimitRequests::class,
        ]);

        // Add any other middleware configurations here
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();