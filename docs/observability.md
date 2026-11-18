# Dove guardare: Jaeger, log, metriche, Grafana, Pulse

[← README](../README.md)

| Lens | URL | Notes |
|---|---|---|
| This app | http://127.0.0.1:8000 | Home page links every hook |
| **Pulse** | http://127.0.0.1:8000/pulse | In-app, no infrastructure, no data leaves the machine |
| **Telescope** | http://127.0.0.1:8000/telescope | Dev-only microscope: requests, queries, cache, logs. `/demo/telescope` generates a fresh request |
| **Jaeger** | http://localhost:16686 | Search **Service** = `telemetry-demo` |
| Jaeger (OTLP) | http://127.0.0.1:4318 | Where the app exports to |
| Outbound `traceparent` | http://127.0.0.1:8000/traceparent | The trace context header the app injects into outgoing HTTP calls — the same `trace_id` you search in Jaeger |
| **Logs and traces** | http://127.0.0.1:8000/logs | One switch: the `trace_id` in your log lines, on and off |
| **Orders → Prometheus** | http://127.0.0.1:8000/orders | Create a random order, record it as a metric, read the last minutes back |
| Prometheus | http://localhost:9090 | Where those metrics end up. Scrapes `/metrics` every 5 s |
| **Grafana** | http://localhost:3000 | The telemetry dashboard (orders, HTTP, queues), plus Prometheus and Jaeger as datasources. No login — anonymous viewer |
| Grafana dashboard | http://localhost:3000/d/telemetry-demo-orders/telemetry-demo-orders | Provisioned from `docker/grafana/dashboards/` |
| Metrics (raw) | http://127.0.0.1:8000/metrics | The Prometheus exposition format, as scraped |
| **Nightwatch, in action** | http://127.0.0.1:8000/nightwatch | Mail, notifications, and a reported error — the three things only Nightwatch sees |
| A real OTLP payload | `docs/otlp-sample/` | The actual bytes of one export, with the hex dump and the strings |
| Nightwatch | https://nightwatch.laravel.com/dashboard | Off by default — see below |

Pulse's dashboard is open to everyone in this app (`viewPulse` is defined to allow all in
`AppServiceProvider`), because a login prompt on stage is a bad look. Do not deploy this thing.

Telescope is installed as a dev dependency and records into the local `telescope_entries` table. It is the
cheapest rung of the ladder — one request, every query and log line, no collector — but it is not production
monitoring, so it stays disabled outside local development (`TELESCOPE_ENABLED`).

### A Jaeger walkthrough that works every time

1. Hit `/slow` once, then `/slow?n_plus_one=0` once.
2. Open http://localhost:16686, service `telemetry-demo`, operation `GET /slow`, **Find Traces**.
3. Open the longest trace. ~114 spans, 108 of them database round trips.
4. Open the short one. ~16 spans. That is `with()`.

### Keeping framework polling out of Jaeger

Not every request deserves a trace. The container healthcheck (`/ping`), the Prometheus scrape and Livewire's
update endpoint are noise: they run on a timer, say nothing about the demo, and would push the interesting
traces off the first page of results. All three are excluded in `config/opentelemetry.php`:

```php
'excluded_paths' => ['ping', 'metrics', 'livewire*/update'],
```

The list is matched with `Request::is()`, so `*` works. Livewire 4 derives its endpoint from `APP_KEY`
(`/livewire-aa73262a/update`), which is why the pattern starts with `livewire` and not `/livewire`. Excluding
the path skips the root span, and the request-scoped instrumentations (queries, HTTP client, cache, queue,
Livewire) check `traceStarted()` first, so no orphan spans are left behind: there is simply no trace.

`excluded_methods` exists too, but it is global: `['POST']` would drop every POST, not a single endpoint.
For a specific endpoint, use a path pattern.

### Seeing the header that leaves the app

Open **http://127.0.0.1:8000/traceparent** (also linked from the home page and the nav bar). It shows the
`traceparent` header OpenTelemetry injected into the last outgoing HTTP call, split into its four parts, with a
direct link to that trace in Jaeger.

Call `/slow` first, then follow the link: the trace id in the middle of the header is the trace you land on. That
is the whole point of the header — the external service receives your trace id, and that is what lets a call you
do not own appear inside your own trace.

### Logs, and how they connect to traces

Open **http://127.0.0.1:8000/logs** — a one-switch demo of the third signal.

OpenTelemetry writes the trace id into Laravel's log context, so every log line carries the trace it belongs to. The
switch turns that on and off, and the button writes a real log line so both states sit side by side. Any line that
has an id links straight to its trace in Jaeger.

Two honest limits, which are half the point:

- **Jaeger stores spans, not logs.** Nothing here ships logs anywhere, and this app has `OTEL_LOGS_EXPORTER=null`.
  What makes the correlation work is the id being *in the line* — that is what a log viewer needs in order to link
  back to a trace.
- **The package injects for HTTP requests and queue jobs, not console commands.** The scheduled heartbeat's log
  lines carry no trace id, because `updateLogContext()` never runs for them. If a line has no id while the switch
  is on, that is usually why.

The switch lives in `storage/app/trace-id-in-logs.json` and is applied in `AppServiceProvider::boot()`. The package
reads the config while the request is being handled, so setting it at boot flips the *real* behaviour rather than
re-implementing it. It is a file and not the cache on purpose: the cache store is the database, and the query count
in the footer has to stay honest.

Reset the page between rehearsals by clearing the log:

```bash
docker compose exec app sh -c ': > storage/logs/laravel.log'
```

### Metrics, and why Prometheus pulls

Open **http://127.0.0.1:8000/orders**. Each click creates a random order and records it as a metric. The page then
reads the numbers back **out of Prometheus**, so what you see is what Prometheus knows.

The order metrics, all in the `demo` namespace:

| Metric | Type | What it is |
|---|---|---|
| `demo_orders_created_total` | counter | how many orders |
| `demo_order_revenue_cents_total` | counter | cumulative revenue, in cents |
| `demo_order_value_cents` | histogram | the distribution of order values |

**Nothing is pushed.** The app exposes `/metrics` and Prometheus scrapes it every 5 seconds — that is the whole
contract. "Revenue in the last 10 minutes" is not stored anywhere; it is a query over a counter that only goes up:

```promql
sum(increase(demo_order_revenue_cents_total[10m])) / 100
```

Three things here are the opposite of how OpenTelemetry works, and they are the interesting part:

- **The counters live in Postgres**, in `prometheus_*` tables, not in the PHP process. A PHP process only lives for
  one request, so the Prometheus client keeps its state in shared storage instead — `App\Support\OrderMetrics` wires
  that up on the connection the app already has.
- **The counters must be exposed even at zero.** A counter that has never been incremented has no row, so it is
  absent from `/metrics` — and a series that first appears already at 5 looks like it never moved, so `increase()`
  over it returns 0. `OrderMetrics::ensureExposed()` touches both counters at zero on every scrape.
- **`increase()` extrapolates.** It works from the samples Prometheus kept, so a burst landing inside one scrape
  interval reads a little high; spread the clicks out and it settles. The exact difference, once Prometheus has been
  running longer than the window, is `sum(x) - sum(x offset 10m)`.

OpenTelemetry's own metrics are off here (`OTEL_METRICS_EXPORTER=null`), and deliberately so: in classic PHP there is
no long-lived process to aggregate in, so nothing ever collects them — the only collector is the worker-mode manager,
which runs under Octane or `queue:work` and nowhere else. Prometheus attacks the same problem from the other side.

The demo also exposes metrics that are not about orders, so the talk's "metrics from routes and queues" is literal:

| Metric | Type | Labels | What it is |
|---|---|---|---|
| `demo_http_requests_total` | counter | `method`, `route`, `status` | requests handled, by route pattern and status |
| `demo_http_request_duration_seconds` | histogram | `method`, `route` | request duration, so Grafana can draw p95 |
| `demo_queue_depth` | gauge | `queue` | jobs waiting in each queue |
| `demo_failed_jobs` | gauge | `queue` | failed jobs per queue |
| `demo_queue_oldest_job_age_seconds` | gauge | `queue` | age of the oldest waiting job |

`App\Http\Middleware\RecordHttpMetrics` records the HTTP families on every web request. The `route` label is the
route *pattern* (`orders`, not `orders/42`), so the number of series stays bounded. Infrastructure routes are
skipped: `/metrics`, `/up`, `/ping`, Pulse and Livewire are not application traffic, and counting the scrape itself
would make every panel wrong. The metric write goes through the Prometheus client's own PDO handle, not Laravel's
query builder, so it does not show up in the footer's query count.

`App\Support\QueueMetrics` reads the same `jobs` and `failed_jobs` tables Laravel uses, so it works with the
database queue driver and does not require Horizon. The gauges are point-in-time values, so they are refreshed on
every scrape. All metric families share `App\Support\MetricsRegistry`, so `/metrics` renders one consistent snapshot
and the reset button wipes all of them together.

The **Reset the demo** button at the bottom of `/orders` puts it back to zero between rehearsals. Orders created
from that page carry a `DEMO-` reference, so it deletes exactly those — the seeded data is untouched — then asks
Prometheus to forget the `demo_*` series and wipes the counters. That includes the HTTP and queue families: the HTTP
counters start again with the reset request itself, and the queue gauges come back on the next scrape.

The order of those last two steps matters. The series is deleted **before** the counters are wiped: the other way
round, a scrape can land in between and Prometheus sees the counter fall back to zero, reads it as a process restart,
and invents a spike out of it.

That needs `--web.enable-admin-api` on Prometheus, which `docker-compose.yml` sets. And the call has to be sent as a
form: Laravel's HTTP client posts JSON by default, which the admin API ignores while still answering `204`, so the
deletion silently does nothing.

To wipe everything, Prometheus history included:

```bash
docker compose rm -sf prometheus
docker volume rm telemetry-demo-prometheus-data
docker compose up -d prometheus
```

### Grafana: a dashboard over the same data, not a fourth backend

Open **http://localhost:3000**. There is no login: the container runs Grafana with anonymous **Viewer** access, so
a stage demo never stops at a password prompt. (`admin` / `admin` still works if you want to edit something.)

Two datasources are provisioned from `docker/grafana/` at startup, so there is nothing to click:

- **Prometheus** (`http://prometheus:9090`, the default datasource) — the same metrics `/orders` reads.
- **Jaeger** (`http://jaeger:16686`) — the same traces as the Jaeger UI. Use **Explore → Jaeger**, service
  `telemetry-demo`, and you get the same waterfall, inside Grafana.

The dashboard **Telemetry demo — HTTP, orders & queues** (uid `telemetry-demo-orders`) shows the order counters,
orders and revenue per minute, p50/p95 order value, HTTP requests per minute, p95 request latency, queue depth,
failed jobs, and the age of the oldest waiting job. It is also the home dashboard, so opening Grafana lands on it.
Every panel is a PromQL query; nothing is stored in Grafana.

That is the point worth making on stage, and it is the same point as the "composable backend" slide: **Grafana is a
query layer**. It is excellent at putting metrics and traces side by side, but it does not receive OTLP and it does
not keep the data. Change the backend and Grafana follows with a datasource change.

If you *do* want to send OTLP to Grafana, that is a different install:

- **Grafana Cloud** accepts OTLP directly (traces, metrics and logs). Point `OTEL_EXPORTER_OTLP_ENDPOINT` at it and
  add the auth header through `OTEL_EXPORTER_OTLP_HEADERS`.
- **Self-hosted Grafana** needs a storage backend that speaks OTLP: **Tempo** for traces, **Loki** for logs,
  **Mimir** for metrics. Point the app at Tempo, or at an OTel Collector in front of it. If you also want to keep
  Jaeger, put a collector in the middle to fan out — this demo deliberately does not, because the Jaeger waterfall
  is the section-6 demo and one trace backend keeps it honest.

The reset button on `/orders` deletes the `demo_*` series. The Grafana panels go flat for a fresh rehearsal, which is
expected; the **Prometheus scrape health** stat is the one that should stay green.

### Readable Jaeger: switch the theme to Light

The Jaeger UI picks its colour theme from your operating system when it has no stored preference. On a
dark-mode machine that selects Jaeger's **dark** theme, which has known low-contrast bugs across several
`jaeger-ui` versions — white text on light panels in the trace view, tag table and a few others. Worse, the
theme toggle is **off by default**, so you would be stuck with it.

`docker/jaeger/ui-config.json` turns the toggle on (`{"themes": {"enabled": true}}`) and `docker-compose.yml`
hands it to Jaeger with `--query.ui-config`.

**Before the talk: click the sun icon at the top-right of the Jaeger header to switch to Light.** The choice
is stored per browser and survives container restarts.

### Making the Pulse cards light up

Pulse's slow-* cards only record anything above a threshold, and a local Postgres answers in microseconds,
so this app lowers them in `.env`:

```
PULSE_SLOW_REQUESTS_THRESHOLD=100
PULSE_SLOW_QUERIES_THRESHOLD=0
PULSE_SLOW_JOBS_THRESHOLD=200
PULSE_SLOW_OUTGOING_REQUESTS_THRESHOLD=0
```

With those values, `/slow` immediately appears in **Slow requests**, the N+1 query shape appears in **Slow
queries** (with the file and line that ran it), and the jobs appear in **Slow jobs**. Session lookups are
filtered out of the slow-queries card in `config/pulse.php` so the card shows the demo's own queries.

### How Pulse works, and the custom Orders card

Pulse is four moving parts, and the dashboard is the least interesting one:

```
domain event ──▶ Recorder ──▶ in-memory buffer ──▶ Ingest driver ──▶ pulse_entries
                                                                  ├─▶ pulse_aggregates
                                                                  └─▶ pulse_values
                                                                          │
                                              Livewire card ◀── Pulse facade
```

- **Recorder** — a class listed in `config/pulse.php`. It subscribes to a Laravel event, or is called
  manually, and writes entries with `Pulse::record(...)`. The entry says which aggregates to keep:
  `->count()`, `->max()`, `->sum()`, `->avg()`.
- **Buffer** — `Pulse::record()` does *not* hit the database. Entries sit in memory and are flushed at the end
  of the request/command, or when the buffer grows past `pulse.ingest.buffer` (5,000).
- **Ingest driver** — `storage` (default: write straight to the DB) or `redis` (push to a stream, consumed by
  `pulse:work`). The demo is on `storage`.
- **Storage** — three tables. `pulse_entries` is the raw event log; `pulse_aggregates` holds pre-computed
  count/min/max/sum/avg buckets for 1 h, 6 h, 24 h and 7 days; `pulse_values` keeps only the latest value per
  key (server CPU/memory, the last order, …).

**The timer:** `pulse:check` is a long-running process, not a cron. It loops once a second, dispatches a
`SharedBeat` (and an `IsolatedBeat` on one server only, via a cache lock) and calls `Pulse::ingest()`. The
`Servers` recorder listens to that beat but throttles itself to every 15 seconds. That is the only place
where Pulse samples the machine; requests and jobs are recorded by their own lifecycle hooks. If you prefer a
cron, `pulse:check --once` works from the scheduler, at the cost of granularity.

**The custom card:** `OrdersController` dispatches `App\Events\OrderPlaced`. The recorder in
`app/Pulse/Recorders/Orders.php` turns it into a count plus a revenue sum, and stores the latest order in
`pulse_values`. `App\Livewire\Pulse\OrdersCard` reads those aggregates back and renders
`resources/views/pulse/orders-card.blade.php`; the dashboard includes it with
`<livewire:pulse.orders cols="4" />`.

Create a few orders on `/orders`: the same click moves the Prometheus counter, the Grafana panel and this
Pulse card. Use that as the talk's "same event, three lenses" moment. The reset button purges both the
`order_placed` and `last_order` Pulse types, so the card and the database agree again.
