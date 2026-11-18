<?php

use App\Http\Middleware\ApplyChaosOverrides;
use App\Http\Middleware\CaptureRequestMetrics;
use App\Http\Middleware\InjectDatabaseLatency;
use App\Http\Middleware\RecordHttpMetrics;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            RecordHttpMetrics::class,
            CaptureRequestMetrics::class,
            ApplyChaosOverrides::class,
            InjectDatabaseLatency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
