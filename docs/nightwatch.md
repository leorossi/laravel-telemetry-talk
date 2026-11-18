# Nightwatch

[← README](../README.md)

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
