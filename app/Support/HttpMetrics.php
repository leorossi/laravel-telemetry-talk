<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Prometheus metrics for the HTTP layer: how many requests, how slow, by route.
 *
 * The route label uses the route *pattern* (`orders`, not `orders/42`), so the
 * number of series stays bounded. Infrastructure routes are skipped: a
 * Prometheus scrape or a container healthcheck is not application traffic.
 */
class HttpMetrics
{
    public const NAMESPACE = 'demo';

    /**
     * Routes that are infrastructure talking to infrastructure. They are not
     * user requests and must not inflate the demo's numbers.
     */
    public const EXCLUDED = [
        'metrics',
        'metrics/*',
        'up',
        'ping',
        'pulse',
        'pulse/*',
        'livewire/*',
        '_ignition/*',
    ];

    public function __construct(protected MetricsRegistry $metrics) {}

    public function record(Request $request, int $status, float $durationSeconds): void
    {
        if ($request->is(...self::EXCLUDED)) {
            return;
        }

        $registry = $this->metrics->registry();

        $method = $request->method();
        // The route pattern keeps the number of series bounded. A request that
        // never resolved a route shares one label, not one series per URL.
        $route = $request->route()?->uri() ?? 'unmatched';
        $status = (string) $status;

        $registry->getOrRegisterCounter(
            self::NAMESPACE,
            'http_requests_total',
            'HTTP requests handled, by method, route and status.',
            ['method', 'route', 'status'],
        )->inc([$method, $route, $status]);

        $registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'http_request_duration_seconds',
            'HTTP request duration, by method and route.',
            ['method', 'route'],
            [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 30],
        )->observe($durationSeconds, [$method, $route]);
    }
}
