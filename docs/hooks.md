# I sette comportamenti

[← README](../README.md)

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
