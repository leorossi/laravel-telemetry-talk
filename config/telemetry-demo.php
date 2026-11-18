<?php

return [
    'external' => [
        'base_url' => env('DEMO_EXTERNAL_BASE_URL', 'https://inventory.example.test'),
        'timeout' => (int) env('DEMO_EXTERNAL_TIMEOUT', 10),
        'retries' => (int) env('DEMO_EXTERNAL_RETRIES', 3),
    ],

    'slow_page_size' => (int) env('DEMO_SLOW_PAGE_SIZE', 50),

    'cache_ttl_seconds' => (int) env('DEMO_CACHE_TTL', 300),

    'prometheus' => [
        // How the app reaches Prometheus, inside the compose network.
        'internal_url' => env('PROMETHEUS_INTERNAL_URL', 'http://prometheus:9090'),
        // How the browser reaches it, from the host.
        'public_url' => env('PROMETHEUS_PUBLIC_URL', 'http://localhost:9090'),
    ],

    'grafana' => [
        // Grafana queries Prometheus and Jaeger; it is not another telemetry
        // backend and stores neither metrics nor traces here.
        'public_url' => env('GRAFANA_PUBLIC_URL', 'http://localhost:3000'),
        'dashboard_url' => env('GRAFANA_DASHBOARD_URL', 'http://localhost:3000/d/telemetry-demo-orders/telemetry-demo-orders'),
    ],

    'chaos' => [
        'n_plus_one' => true,
        'external_latency_ms' => 0,
        'external_failure' => false,
        'db_sleep_ms' => 0,
        'job_failure_rate' => 0,
    ],

    'chaos_cache_key' => 'demo:chaos',

    'chaos_labels' => [
        'n_plus_one' => 'Slow endpoint: lazy-load relations (N+1)',
        'external_latency_ms' => 'External inventory API latency',
        'external_failure' => 'External inventory API returns 503',
        'db_sleep_ms' => 'Every request runs a slow query',
        'job_failure_rate' => 'Queued job failure rate',
    ],
];
