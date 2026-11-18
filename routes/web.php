<?php

use App\Http\Controllers\CacheDemoController;
use App\Http\Controllers\CachedReportController;
use App\Http\Controllers\ChaosPanelController;
use App\Http\Controllers\FailingReportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\NightwatchDemoController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\QueueDemoController;
use App\Http\Controllers\SlowOrdersController;
use App\Http\Controllers\TelescopeDemoController;
use App\Http\Controllers\TraceparentController;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Http\Middleware\Sample;

/*
| Nightwatch only collects the routes in the first group below. Everything
| else is wrapped in Sample::never(), so the dashboards (Telescope, Pulse,
| Livewire polls), the healthchecks and the talk's other pages never spend
| event quota. NIGHTWATCH_REQUEST_SAMPLE_RATE=0.0 in .env is the second line
| of defence, for package routes that are not declared in this file.
*/

Route::middleware(Sample::always())->group(function (): void {
    Route::get('/slow', SlowOrdersController::class)->name('demo.slow');

    Route::get('/orders', [OrdersController::class, 'index'])->name('demo.orders');
    Route::post('/orders', [OrdersController::class, 'store'])->name('demo.orders.store');
    Route::post('/orders/reset', [OrdersController::class, 'reset'])->name('demo.orders.reset');

    // The three Nightwatch-only events, on manual clicks. Mail and
    // notifications are child events, so they need their request sampled.
    Route::post('/nightwatch/receipt', [NightwatchDemoController::class, 'receipt'])->name('demo.nightwatch.receipt');
    Route::post('/nightwatch/notify', [NightwatchDemoController::class, 'notify'])->name('demo.nightwatch.notify');
    Route::post('/nightwatch/report', [NightwatchDemoController::class, 'report'])->name('demo.nightwatch.report');
});

Route::middleware(Sample::never())->group(function (): void {
    Route::get('/', HomeController::class)->name('home');

    // Liveness probe for the Docker healthcheck. It runs every 5 seconds, all
    // day, so it is not application traffic and must not spend event quota.
    // Point the healthcheck here instead of at a demo route.
    Route::get('/ping', fn () => response('pong', 200));

    Route::get('/fast', CachedReportController::class)->name('demo.fast');
    Route::get('/cache', CacheDemoController::class)->name('demo.cache');
    Route::get('/fail', FailingReportController::class)->name('demo.fail');

    Route::get('/jobs', [QueueDemoController::class, 'index'])->name('demo.jobs');
    Route::post('/jobs/dispatch', [QueueDemoController::class, 'dispatch'])->name('demo.jobs.dispatch');

    Route::get('/traceparent', TraceparentController::class)->name('demo.traceparent');

    Route::get('/logs', [LogsController::class, 'index'])->name('demo.logs');
    Route::post('/logs/toggle', [LogsController::class, 'toggle'])->name('demo.logs.toggle');
    Route::post('/logs/write', [LogsController::class, 'write'])->name('demo.logs.write');

    // Prometheus scrapes this every 5 seconds. Like /ping, it is infrastructure
    // talking to infrastructure. Prometheus itself is unaffected: sampling only
    // changes what Nightwatch ingests.
    Route::get('/metrics', MetricsController::class)->name('demo.metrics');

    // The specimen for the Telescope slide: a few queries, a cache read and a
    // log line in one request. The UI itself is Telescope's own /telescope route.
    Route::get('/demo/telescope', TelescopeDemoController::class)->name('demo.telescope');

    Route::get('/nightwatch', [NightwatchDemoController::class, 'index'])->name('demo.nightwatch');

    Route::get('/chaos', [ChaosPanelController::class, 'index'])->name('demo.chaos');
    Route::post('/chaos/apply', [ChaosPanelController::class, 'apply'])->name('demo.chaos.apply');
    Route::post('/chaos/reset', [ChaosPanelController::class, 'reset'])->name('demo.chaos.reset');
});
