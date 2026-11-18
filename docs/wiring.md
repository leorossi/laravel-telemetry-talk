# Wiring dei pacchetti e test

[← README](../README.md)

| Package | Config | Notes |
|---|---|---|
| `laravel/pulse` | `config/pulse.php` | Requires `php artisan migrate` (publishes its own tables). Dashboard cards reordered in `resources/views/vendor/pulse/dashboard.blade.php` so slow jobs and slow queries sit near the top. |
| `keepsuit/laravel-opentelemetry` | `config/opentelemetry.php` | Auto-instrumentation for HTTP server, HTTP client, queries, cache, queue, events, views, console. Sampler is `always_on` so every demo request is traceable. |
| `laravel/nightwatch` | `config/nightwatch.php` | Config-first, enabled by `NIGHTWATCH_ENABLED` + `NIGHTWATCH_TOKEN`. |

The demo's own wiring:

- `App\Support\Chaos` — the chaos switches, backed by the cache.
- `App\Http\Middleware\ApplyChaosOverrides` — reads `?switch=value` overrides per request.
- `App\Http\Middleware\InjectDatabaseLatency` — runs the slow query when `db_sleep_ms` is set (Postgres only).
- `App\Http\Middleware\CaptureRequestMetrics` — powers the query counter in the footer.
- `App\Http\Middleware\RecordHttpMetrics` — records the HTTP counter and latency histogram for every app route.
- `App\Support\SimulatedExternalService` — the stubbed external API, registered at boot.
- `App\Support\OutboundTraceparent` — records the last outbound `traceparent` header, shown on `/traceparent`.
- `App\Support\TraceIdInLogs` — the file-backed switch behind `/logs`; applied to the OTel config at boot.
- `App\Support\MetricsRegistry` — the one Prometheus registry, backed by the app's Postgres; shared by every family.
- `App\Support\OrderMetrics` — records the order counters and histogram.
- `App\Support\HttpMetrics` — records requests and latency, by route pattern.
- `App\Support\QueueMetrics` — exposes queue depth, failed jobs and the oldest waiting job as gauges.
- `App\Support\PrometheusQuery` — a small read-only client for the Prometheus HTTP API, used by `/orders`.
- `App\Support\RequestMetrics` — the per-request query count and elapsed time.
- `App\Pulse\Recorders\Orders` + `App\Livewire\Pulse\OrdersCard` — the custom Pulse recorder and card
  behind the Orders panel, fed by `App\Events\OrderPlaced`.

---

## Tests and formatting

```bash
docker compose exec app php artisan test      # 22 tests
docker compose exec app vendor/bin/pint       # format; --test to check only
```

The same commands work from your host shell if you have local PHP and `vendor/`.

Tests run against in-memory SQLite with Pulse and Nightwatch disabled (`phpunit.xml`), so they do not touch
the Docker Postgres.
