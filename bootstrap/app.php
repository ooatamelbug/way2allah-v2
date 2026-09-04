<?php

use App\Http\Middleware\MonitorsRequestPerformance;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Roadmap task 6.8 — /backup.php is a machine-to-machine content-
        // backup API; its external client cannot supply a Laravel CSRF
        // token (it never has, per the confirmed legacy contract), so
        // this exact path is exempted rather than left to 419 on every
        // real call. No other route is exempted.
        $middleware->validateCsrfTokens(except: [
            'backup.php',
        ]);

        // Enhancement Batch E-01 — slow-request detection. Prepended so
        // the measured window covers as much of the stack as possible:
        // it starts the timer on the way in and logs (only when over the
        // configured threshold) from terminate(), after the response has
        // already been sent to the client. Self-disables entirely via
        // config('performance.enabled').
        $middleware->prepend(MonitorsRequestPerformance::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
