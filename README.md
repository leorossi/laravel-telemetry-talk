# Laravel al microscopio

**Dove sono finiti i quattro secondi?** Talk e applicazione demo per **LaravelDay 2026** (18 novembre, 15:10–15:50).

Una piccola app Laravel, sette comportamenti costruiti apposta e tre lenti sulla stessa richiesta: **Pulse**, **Nightwatch** e **OpenTelemetry + Jaeger**. Il filo conduttore sono log, metriche e tracce: gli uni dicono *che*, gli altri *quanto*, le tracce *dove*.

| Cosa | Dove |
|---|---|
| Slide (PDF) | [`slides/slides-export.pdf`](slides/slides-export.pdf) (dark) · [`slides/slides-export-light.pdf`](slides/slides-export-light.pdf) (light) |
| Run-of-show e tempi | [`docs/outline.md`](docs/outline.md) |
| App demo e stack Docker | questo README, da [The demo app](#the-demo-app) in poi |
| Q&A, approfondimenti e materiale extra | [`EXTRA.md`](EXTRA.md) |

## Avviare la demo

```bash
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

L'app risponde su <http://127.0.0.1:8000>. Jaeger è su <http://localhost:16686>, Prometheus su <http://localhost:9090>, Grafana su <http://localhost:3000>. I dettagli, i comandi e il troubleshooting sono nelle sezioni che seguono.

---

## The demo app

One small Laravel 12 app, seven deliberately shaped behaviours, and three lenses that look at the same
request: **Pulse**, **OpenTelemetry → Jaeger**, and **Nightwatch**.

Nothing here needs an account, and nothing needs the internet. The whole stack — the app, Postgres, Jaeger,
Prometheus and Grafana — runs in Docker, and your working tree is mounted into the app container so you can edit
code and reload.

---

## What is in the stack

`docker compose up -d` starts nine containers:

| Service | Image | Ports | Why |
|---|---|---|---|
| `app` | built from `Dockerfile` | `8000` | The Laravel app, served by `php artisan serve` |
| `queue` | same image | — | `php artisan queue:work` |
| `scheduler` | same image | — | `php artisan schedule:work` |
| `pulse` | same image | — | `php artisan pulse:check` — the heartbeat behind Pulse's Servers card |
| `nightwatch` | same image | — | `php artisan nightwatch:agent` — idle until Nightwatch is enabled |
| `postgres` | `postgres:17-alpine` | `5432` | The database, the queue, the cache, the sessions — and the Prometheus counters |
| `jaeger` | `jaegertracing/all-in-one:1.76.0` | `16686` (UI), `4317` (OTLP gRPC), `4318` (OTLP HTTP) | Trace backend, receives OTLP directly |
| `prometheus` | `prom/prometheus:v3.15.1` | `9090` (UI) | Metric backend. It **pulls** from the app's `/metrics` every 5 s |
| `grafana` | `grafana/grafana:12.2.5` | `3000` (UI) | Dashboard layer over Prometheus and Jaeger. It **stores nothing** itself |

There is **no Redis**, **no separate OTel collector**, and nothing is pushed to Prometheus. Grafana is a
reader: its two datasources are Prometheus (metrics) and Jaeger (traces), both provisioned from
`docker/grafana/`.

- **Cache driver:** `database` (the `cache` table in Postgres)
- **Queue driver:** `database` (the `jobs` / `job_batches` / `failed_jobs` tables in Postgres)
- **Session driver:** `database`
- **Telemetry path:** app → OTLP/HTTP → Jaeger all-in-one (`http://jaeger:4318` on the compose network)

Metrics and logs are exported to a no-op exporter (`OTEL_METRICS_EXPORTER=null`, `OTEL_LOGS_EXPORTER=null`).
Traces are the point of the Jaeger lens, and Jaeger stores spans in memory only — restarting the container
loses them, which is exactly what you want on stage.

### How the app container is put together

- **`vendor/` is installed at build time** from `composer.json` + `composer.lock` and handed to the running
  containers through a named volume, so it never touches your working tree.
- **Your working tree is bind-mounted** over the image at `/var/www/html`. Edit a controller or a Blade view,
  reload the page, and the change is live — no rebuild, no restart. (`opcache.enable_cli` is off and PHP's
  built-in server re-reads source files per request.)
- **Rebuild only when dependencies change:** `docker compose build` after editing `composer.json` /
  `composer.lock`, or when `Dockerfile`, `.dockerignore` or `docker/entrypoint.sh` change. `vendor/` lives in
  the `telemetry-demo-vendor` volume, and Docker only fills a volume when it is empty, so refresh it in the
  same breath:

  ```bash
  docker compose build
  docker volume rm telemetry-demo-vendor
  docker compose up -d
  ```
- **Inside the network the service names are the hostnames:** `postgres`, `jaeger`, `prometheus` and
  `grafana`. `docker-compose.yml` sets `DB_HOST=postgres` and
  `OTEL_EXPORTER_OTLP_ENDPOINT=http://jaeger:4318` for the containers, overriding the `127.0.0.1` values in
  `.env` (real environment variables win over `.env`). Because Postgres and Jaeger also publish their ports,
  the same `.env` still works for commands you run on the host. Grafana reaches the other two at
  `http://prometheus:9090` and `http://jaeger:16686`.

---

## Run it

Everything runs in Docker; the only two files you may need on first run are `.env` and an app key.

### 1. Stack and dependencies

```bash
docker compose up -d          # builds the app image, starts postgres + jaeger + prometheus + grafana + app + queue + scheduler + pulse + nightwatch
docker compose ps             # wait for app and postgres to show "healthy" (~10 seconds)
cp .env.example .env          # skip if .env already exists
docker compose exec app php artisan key:generate   # skip if APP_KEY is already set
```

`vendor/` is installed into the image at build time — you do **not** need to run `composer install`.

### 2. Database

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force    # 30 customers, 240 orders, ~1070 order items
```

Seeding takes about two seconds. Re-seed from scratch with
`docker compose exec app php artisan migrate:fresh --seed --force`.

### 3. The long-running processes

All three already run as containers. Tail them whenever you want to watch what the demo is doing:

```bash
docker compose logs -f app queue scheduler
```

- `app` serves `http://127.0.0.1:8000` with `php artisan serve --no-reload`. `PHP_CLI_SERVER_WORKERS=4` in
  `.env` makes it serve requests concurrently, so the “hit it 10 times” buttons are genuinely parallel.
- `queue` runs `php artisan queue:work` for the chain and the batch.
- `scheduler` runs `php artisan schedule:work` for `demo:heartbeat` every minute.

### 4. Sanity check

```bash
curl -s localhost:8000/fast | grep -o 'queries <b>[0-9]*</b>'
```

Every page has a footer showing the query count and elapsed time for that request. That footer is the
cheapest useful telemetry in the app, and it is what you will point at on stage.

### Editing code and re-running commands

Your working tree is mounted into `app`, `queue` and `scheduler`, so edit a file and just reload the page
(or re-run the `curl`) — nothing to rebuild or restart. Run one-off commands in the app container:

```bash
docker compose exec app php artisan demo:chaos
docker compose exec app php artisan demo:heartbeat
docker compose exec app php artisan tinker
```

Everywhere below, `php artisan …` means either your host shell (if you have local PHP and `vendor/`) or
`docker compose exec app php artisan …` inside the container. Rebuild the image only when dependencies or the
Docker files change:

```bash
docker compose build && docker compose up -d
```

### Where the application logs go, and how to watch them

Laravel writes to `storage/logs/laravel.log` — the `single` channel — not to stdout. That is why
`docker compose logs -f app` on its own shows the PHP built-in server's request lines but none of your `Log::info`
calls.

`LOG_STACK=single,stderr` in `.env` writes to both, so the application log reaches the container's stderr as well:

```bash
docker compose logs -f app
```

Two things to know about that view:

- **It is the raw stream.** Multi-line entries (an exception stack trace is 60+ lines) arrive as they are, and the
  stderr channel uses Monolog's default line format, so those lines have no `[timestamp]` prefix. Grepping a
  `--tail` window often misses the *header* of an entry, because the header sits at the top of a very tall entry.
- **The file is still written**, so the `/logs` page is unaffected either way.

Changing `LOG_STACK` needs a restart: Dotenv is immutable, and the serve process keeps the environment of its first
request, so the new value never gets picked up otherwise.

```bash
docker compose restart app queue scheduler
```

For just the application log — timestamps, no PHP server noise, no restarts — tail the file on the host. The working
tree is bind-mounted, so it is right there:

```bash
tail -f storage/logs/laravel.log
```


---

## The seven hooks

Every one of these is linked from the home page at **http://127.0.0.1:8000/**, so the demo can be driven
from a single tab.

| # | Hook | URL | What it exists to show |
|---|---|---|---|
| 1 | Fast endpoint | `/fast` | A cached rollup served in ~3 queries. The baseline. |
| 2 | Slow endpoint | `/slow` | **108 SQL spans** plus a call to an external API. |
| 3 | Failing endpoint | `/fail` | An unhandled exception, with a trace id in the log line. |
| 4 | Queued jobs | `/jobs` | A **chain** of 3 jobs and a **batch** of 8, with retries. |
| 5 | Cache miss → hit | `/cache` | 60 day-by-day queries on a miss, one cache read on a hit. |
| 6 | Scheduled task | `php artisan demo:heartbeat` | Runs every minute via `schedule:work`. |
| 7 | Chaos panel | `/chaos` | Runtime switches: latency, failures, N+1 on/off. |

### 1 + 5 — the two cache flows

- `/fast` is the boring baseline. `?refresh=1` flies the cache and makes the next request pay for the
  rebuild. The page prints what the rebuild costs, so “cache hit” is a number, not a feeling.
- `/cache` is the teaching version. `?clear=1` clears the key and recomputes; the next plain request is a
  hit. The cached payload is sixty day-by-day revenue queries, so the difference is loud.

Both use the `database` cache store, so cache reads and writes also show up as SQL spans in Jaeger.

### 2 — the slow endpoint

`/slow` renders 50 orders, and by default it loads each order's customer and items lazily:

```
GET /slow                    106 queries
GET /slow?n_plus_one=0         8 queries
```

The footer prints the live count. In Jaeger the naive version is a trace with ~114 spans, 108 of them
`SELECT`, stacked one after another; the eager version collapses to ~16 spans. The one-line fix lives in
`app/Services/OrderReportService::recentOrders()`.

Jaeger counts **108** `SELECT` spans where the footer counts **106** queries, and both are right — the two
tools look at slightly different windows. `CaptureRequestMetrics` is *appended* to the `web` middleware
group, so it starts after `StartSession`: the two session lookups happen before it is watching, and Jaeger
sees them. Two instruments, slightly different counts. Worth saying out loud on stage.

The same request then calls `https://inventory.example.test/api/stock/{sku}` through Laravel's HTTP client.
That host is **stubbed in-process** (`app/Support/SimulatedExternalService.php`) so it needs no internet and
cannot deadlock the dev server — but it is still a real outbound HTTP call with a real client span, real
retries, and real Pulse/Nightwatch records.

### 3 — the failing endpoint

`/fail` throws `App\Exceptions\WarehouseSyncFailedException`. With `APP_DEBUG=true` you get Laravel's error
page, and the log line carries the OpenTelemetry `trace_id` so you can jump straight to the trace.

### 4 — the queued jobs

`/jobs` shows the queue depth, the failed-job count and live progress of the last batch. The dispatch button
sends:

- a **chain**: `ReserveStockJob` → `ChargePaymentJob` (3 tries, backoff 1s/3s) → `SendReceiptJob`
- a **batch**: 8 × `SyncInventoryJob`, one per random SKU, with `then()` and `catch()` callbacks

Each job sleeps 150–400 ms on purpose so “slow jobs” has something to show, and each one calls the external
inventory API so the job traces are interesting too.

### 6 — the scheduled task

`demo:heartbeat` runs every minute (`routes/console.php`). It rolls up orders, items, customers and today's
revenue, writes the result to the cache, logs a line, and the home page displays the last run — so you can
prove the scheduler works without leaving the browser.

Run it by hand any time:

```bash
php artisan demo:heartbeat
```

### 7 — the chaos panel

**http://127.0.0.1:8000/chaos** — the presenter's remote control. Every switch is a button, and every
change is stored in the cache, so it applies to the next request from any tab or browser. No deploy, no
restart.

| Switch | Values | What it does |
|---|---|---|
| `n_plus_one` | on / off | Toggles the N+1 in `/slow`. The off position is the eager-loaded fix. |
| `external_latency_ms` | 0 / 500 / 2000 / 5000 | Sleeps inside the simulated external API call. |
| `external_failure` | on / off | The external API returns 503. The client retries 3×, then the page shows the error. |
| `db_sleep_ms` | 0 / 250 / 1000 / 3000 | Runs `select pg_sleep(...)` before every request (except `/chaos` and `/pulse`). |
| `job_failure_rate` | 0 / 25 / 50 / 100 % | Chance that `ChargePaymentJob` throws, producing retries and failed jobs. |

**Reset everything** with the big button at the bottom of the panel, or from the terminal:

```bash
php artisan demo:chaos               # show every switch
php artisan demo:chaos db_sleep_ms 250
php artisan demo:chaos --reset
```

**One-off overrides** need no state at all. Any switch can be passed as a query parameter on any demo URL:

```
http://127.0.0.1:8000/slow?n_plus_one=0&db_sleep_ms=250
http://127.0.0.1:8000/slow?external_latency_ms=5000
```

That applies to that single request and is never written to the cache — useful when you want a clean
“before” and “after” waterfall without touching the switches mid-sentence.

---

## Where to look

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

---

## Turning Nightwatch on

Nightwatch is installed and configured, but **disabled by default** so the app runs with zero accounts. To flip it on:

```bash
# 1. put your token in .env, and turn it on
NIGHTWATCH_TOKEN=your-token-here
NIGHTWATCH_ENABLED=true

# 2. restart the app containers — .env is read once, per process
docker compose restart app queue scheduler

# 3. check it
docker compose exec app php artisan nightwatch:status
```

If the token is blank the package registers no hooks and the app behaves exactly as it does now.

**The agent runs as its own container** (`telemetry-demo-nightwatch`), so there is nothing to start by hand and
nothing to install on your laptop:

```bash
docker compose logs -f nightwatch
```

You want `Authentication successful` once at startup, then an `Ingest successful` line per batch. `docker-compose.yml`
points the app services at `nightwatch:2407`; the agent owns the outbound connection to Laravel's ingest.

That is the same shape as an OpenTelemetry collector: the app posts to a **local** hop with a 0.5 s timeout and a
500-event buffer, and the agent deals with the slow network. It is also why "no infrastructure" is true with a
nuance — the collector exists, it is just theirs and it runs next to you.

### What Nightwatch is allowed to collect

The plan is a fixed monthly event allowance, and a dev machine can burn it without
anyone clicking anything: `/ping` runs every 5 seconds, Prometheus scrapes `/metrics`, the
Telescope and Pulse dashboards query their own tables, and the N+1 demo emits a query per
order. One event is not one request — it is the request *plus* every query, cache hit, log
line and job it triggers. So the app opts in explicitly:

- **Routes.** Only `/orders`, `/slow` and the three `/nightwatch` POSTs carry `Sample::always()`
  in `routes/web.php`. Everything else sits in a `Sample::never()` group. The Telescope and
  Pulse dashboards get the same middleware in their config files, because their routes are
  registered by the packages, not in `routes/web.php`.
- **Default rate.** `NIGHTWATCH_REQUEST_SAMPLE_RATE=0.0` is the second line of defence:
  package routes that are not declared in `routes/web.php` (Livewire polls, `/up`, bot
  traffic) are not sampled either.
- **Filters.** Queries and outgoing requests are collected — that is the `/slow` story, N+1
  plus the external call. Cache events are off. `AppServiceProvider` additionally rejects
  queries against `telescope_*`, `pulse_*`, `jobs` and `cache`, so the infrastructure noise
  stays out even with query collection on.
- **Exceptions.** `NIGHTWATCH_EXCEPTION_SAMPLE_RATE=1.0` ignores the request sample rate
  by design, so `/fail` and `/nightwatch/report` still land on the dashboard as issues
  without the rest of their request being collected.

Mail and notifications are **child events**: they are only collected when their request is
sampled. That is why `/nightwatch/receipt` and `/nightwatch/notify` are in the whitelist —
they are manual clicks, and without a sampled request their mail and notification events
would never leave the app. The `/nightwatch` page itself is not sampled: rendering it
produces nothing worth paying for.

### The `/nightwatch` page

**http://127.0.0.1:8000/nightwatch** fires three things that only Nightwatch can show:

- a **mail**, through `Mail::raw`
- a **notification**, a real `App\Notifications\ReceiptReady`
- an error that is **caught and reported**, so the request finishes normally and the customer never sees a 500

The third one throws from `App\Services\CheckoutService::reserve()`, two frames below the controller, and asks the
inventory service for 9999 units so it always trips. The throw lands on a real line of real code, which is what makes
the source code shown on the dashboard worth looking at. The exception's `getFile()`/`getLine()` point at
`CheckoutService.php:30`; PHP's own trace frame `#0` is the *call site* in the controller, which is worth knowing if
you ever read one of these by hand.

Neither Jaeger nor Pulse has any idea that mail exists, which is the whole reason the page is there.

---

## How the telemetry is wired

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

---

## The talk slides

The slides are published as PDF only (the Slidev source is not part of this
repository):

- [`slides/slides-export.pdf`](slides/slides-export.pdf) — dark theme
- [`slides/slides-export-light.pdf`](slides/slides-export-light.pdf) — light theme

`docs/outline.md` is the run-of-show: structure, timing, demo steps and
fallbacks.

The deck now ends on **“Grazie”** and the **glossary**. The Q&A, the vendor
notes and the “Se c'è tempo” material live in [`EXTRA.md`](EXTRA.md), so the talk
stays inside its 40 minutes.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| `SQLSTATE[08006]` / cannot connect | `docker compose up -d` and wait for `docker compose ps` to show postgres as *healthy*. |
| No traces in Jaeger | Check `docker compose logs jaeger`, then `curl -s -o /dev/null -w '%{http_code}' localhost:4318/v1/traces` (405 means the receiver is alive). A trace is normally visible within a second or two: in classic PHP the batch processor's 5 s timer never fires because the request ends first, so spans are flushed at shutdown. |
| Prometheus shows no data | Open http://localhost:9090/targets and check the `telemetry-demo` target is **up**. If it is, wait for a scrape (5 s) — `increase()` needs at least two samples in the window before it reports anything. |
| Grafana dashboard is empty or says “Datasource error” | Check `docker compose logs grafana`, then open http://localhost:3000/connections/datasources and use **Save & test** on Prometheus and Jaeger. The containers must be named `prometheus` and `jaeger` on the compose network. |
| Grafana shows a login page | Anonymous access did not apply. Run `docker compose up -d --force-recreate grafana`; the `GF_AUTH_ANONYMOUS_*` variables are in `docker-compose.yml`. |
| Jaeger UI text is white-on-white / hard to read | You are in Jaeger's dark theme, which has known contrast bugs. Click the sun icon at the top-right of the Jaeger header to switch to Light. |
| `/slow` has only ~3 queries | The N+1 has been switched off. Check `php artisan demo:chaos` and reset it. |
| Jobs never run | The `queue` container is not up (`docker compose up -d queue`), or `php artisan demo:chaos job_failure_rate 100` is still set. `php artisan queue:retry all` after a reset. |
| Home page says “No heartbeat yet” | The `scheduler` container is not up (`docker compose up -d scheduler`). |
| Every page is slow | `db_sleep_ms` is still on. `php artisan demo:chaos --reset`. |
| Stale config after editing `.env` | `php artisan config:clear`, then `docker compose restart app queue scheduler`. |
| Changes to `composer.json` / `composer.lock` ignored | Rebuild and refresh the vendor volume: `docker compose build && docker volume rm telemetry-demo-vendor && docker compose up -d`. |
| Starting over | `docker compose down -v && docker compose up -d`, then `php artisan migrate:fresh --seed --force`. |
