# Laravel al microscopio — talk outline

**LaravelDay 2026 · 18 November · 15:10–15:50 (40 min) · Italian**

This is the run-of-show: structure, timing, demo steps and fallbacks. The
line-by-line script is in the speaker notes of each slide (source not in this repo)
(Italian). If this file and the deck disagree, the deck wins.

The published abstract is the contract with the audience. The deck delivers it
in this order: **three signals first, then three lenses over the same request.**

---

## The promise, and where the talk pays it off

| Abstract promise | Where it lands |
|---|---|
| Logs enriched with context and a trace id | Section 10: `/logs` and “La stessa riga di log” |
| Metrics for Prometheus from models, HTTP routes and queues | Section 8: `demo_order_*`, `demo_http_*`, `demo_queue_*` |
| Traces (OTel) for the whole request, including async jobs | Section 7: the waterfall + “La stessa traccia attraversa la coda” |
| Grafana and Jaeger dashboards | Section 8: “Grafana non è un quarto strumento” |
| A short comparison with commercial APM | Section 6 (Nightwatch) + Section 9 (the map) |
| A failing background job, and degradation before users notice | Section 6 (failed-job callout) + Section 5 (trend, not alert) |

Two honest limits, say them on stage if asked:

- **Horizon is not a rung on the ladder slide.** The demo uses the database
  queue driver and exposes queue depth, failed jobs and the age of the oldest
  job through Prometheus. Horizon is mentioned only in the appendix, as “when
  queues become your world”, and never demoed.
- **Nightwatch is hosted and commercial.** It is a lens for comparison, not the
  open-source spine of the talk. The map in section 9 makes that explicit.

---

## Runtime at a glance

**Slot: 40 minutes, 15:10–15:50.** The deck currently carries roughly 38–41
minutes of content, depending on how much of section 8 you keep. Lock the split
after a timed rehearsal. If Q&A is inside the 40, cut from section 8 first —
never from the waterfall, the custom Pulse card or the closing log line.

| # | Section | Target | Cumulative | Cut order |
|---|---|---|---|---|
| 1 | Cold open: “It works on my machine” | 3 | 3 | keep |
| 2 | The three shapes of telemetry | 5 | 8 | cut metrics examples to one |
| 3 | Laravel's observability ladder | 5 | 13 | cut one rung to a sentence |
| 4 | The demo app tour | 3 | 16 | cut to 1: show `/slow` + `/chaos` |
| 5 | Lens 1 — Pulse | 6 | 22 | compress the storage tables to one sentence; keep the recorder and the custom card |
| 6 | Lens 2 — Nightwatch | 4 | 26 | one error only; skip mail/notification |
| 7 | Lens 3 — OpenTelemetry + Jaeger | 6 | 32 | protect the waterfall |
| 8 | Inside OpenTelemetry + Grafana | 3 | 35 | cut buffer/sampler/worker-mode first |
| 9 | Three lenses, two axes | 3 | 38 | do not add new content |
| 10 | Monday morning + closing log line | 2 | 40 | protect the log line |

If you are past the cumulative number, move on. The talk is designed to lose
three minutes; it does not survive losing eight. The two new Pulse slides are
one minute of explanation and one minute of code; if you must cut, keep the
custom card and drop the three-table detail.

---

## Glossary — define these the first time they appear

Say the definitions out loud, in these words. Do not assume the audience knows
them.

- **Telemetry** — data your app sends out about itself while it runs. Logs,
  metrics and traces are the three kinds.
- **Log** — a timestamped line of text about one event. “User 42 logged in.”
- **Metric** — a number that adds up or averages over time. “Requests per
  minute”, “average response time”. Cheap to store, loses detail.
- **Trace** — the full story of one request, from start to finish, across every
  piece of code it touched.
- **Span** — one timed step inside a trace. “Ran this SQL query: 340 ms.” A
  trace is a tree of spans.
- **Trace id** — the id shared by every span in one trace. Put it in a log line
  and the line can link back to the trace.
- **`traceparent`** — the W3C header that carries the trace id and span id. OTel
  injects it into outgoing HTTP calls; in a queue job it travels in the payload.
- **Instrumentation** — the code that creates spans and metrics. Doing it by
  hand is “manual instrumentation”; a package doing it for you is
  “auto-instrumentation”.
- **OpenTelemetry (OTel)** — an open, vendor-neutral standard for producing
  telemetry. Think “PDF for traces”: anyone can write it, anyone can read it.
- **OTLP** — OpenTelemetry Protocol. The wire format apps use to *send*
  telemetry to a backend. It is the shipping box, not the contents.
- **Collector** — a small service that receives OTLP and forwards it to one or
  more backends. A mail sorting office.
- **Backend** — the tool that stores and displays telemetry, e.g. Jaeger.
- **Jaeger** — an open-source trace viewer. Its signature screen is the
  **waterfall**: bars stacked left to right showing what took how long.
- **Prometheus** — an open-source metrics backend. It **pulls**: the app exposes
  `/metrics`, Prometheus scrapes it every few seconds. Nothing is pushed.
- **Grafana** — a dashboard and query layer. It reads Prometheus and Jaeger and
  stores nothing itself. Not a fourth backend.
- **Counter** — a number that only goes up. Orders created, requests handled.
- **Gauge** — a number that goes up and down. Queue depth, failed jobs, the age
  of the oldest waiting job.
- **Histogram** — buckets of observations, used to compute percentiles such as
  p95. Request duration is a histogram.
- **p95 latency** — the response time that 95% of requests came in under. A
  number that exposes your slow tail, which averages hide.
- **Queue depth** — how many jobs are waiting to run.
- **Failed job** — a job that exhausted its retries and was written to
  `failed_jobs`. It does not disappear; it sits there until you retry or forget
  it.
- **N+1 query** — a performance bug where you run one query to get a list, then
  one more query per row. “100 orders” becomes 101 queries.
- **Pulse** — a first-party Laravel package that shows live performance and
  usage numbers inside your own app.
- **Recorder (Pulse)** — the class that listens to a Laravel event and writes
  Pulse entries, e.g. `SlowQueries` or the demo's `Orders`. It declares which
  aggregates to keep with `count()`, `sum()`, `max()`, `avg()`.
- **Entry vs value (Pulse)** — an **entry** is a timestamped event with an
  optional number (`slow_query`, `order_placed`); a **value** is only the latest
  value for a key (`system`, `last_order`). Entries can be aggregated, values
  cannot.
- **Ingest driver (Pulse)** — how the in-memory buffer reaches storage:
  `storage` writes directly, `redis` publishes to a stream consumed by
  `pulse:work`.
- **`pulse:check`** — the long-running process behind the dashboard. It beats
  once a second, flushes the buffer, and lets the `Servers` recorder sample CPU
  and memory every 15 seconds. Not a cron job, though `--once` can be scheduled.
- **Aggregate bucket (Pulse)** — a pre-computed count/min/max/sum/avg for a time
  window (1 h, 6 h, 24 h, 7 days), stored in `pulse_aggregates`. Cards read
  buckets, not raw entries, so they stay fast after entries are trimmed.
- **Telescope** — a first-party Laravel package that shows a *debug* view of one
  local request: queries, jobs, mail, cache. Local development tool.
- **Horizon** — a first-party Laravel package that supervises and displays
  **queues**. Requires Redis. A rung on the ladder, not the demo's driver.
- **Nightwatch** — Laravel's hosted, first-party production monitoring service.
  One `composer require`, opinionated defaults, data leaves your server for
  Laravel's.
- **Laravel-native** — built by the Laravel team, follows Laravel conventions,
  knows about Eloquent, queues, Octane, and so on.
- **Sampling** — keeping only a fraction of traces to save money. “1 in 100.”
- **Sampled flag** — the last byte of `traceparent`. `01` means the sampler
  decided to record and export this trace; `00` means it did not. Propagation
  continues either way: the downstream service still receives the trace id.
  This demo runs `always_on`, so you will only see `01` on stage.
- **Head-based sampling** — deciding to keep or drop a trace at the *start* of
  the request, before you know if it will be interesting.

---

## Before you walk on stage — setup checklist

Do this the night before, and again 30 minutes before the talk.

- [ ] Docker Compose stack up: `postgres`, `jaeger`, `prometheus`, `grafana`,
      `app`, `queue`, `scheduler`, `pulse`, `nightwatch`. `docker compose ps` should show
      Postgres healthy and the app healthy. There are **no host processes** to
      start: `queue:work` and `schedule:work` are containers now.
- [ ] `docker compose exec app php artisan migrate` has run.
- [ ] Browser tabs, each loaded once so nothing is cold:
      app home, `/orders`, `/logs`, `/jobs`, `/nightwatch`, Pulse, Jaeger,
      Grafana, `/chaos`.
- [ ] Grafana opens on the provisioned dashboard
      “Telemetry demo — HTTP, orders & queues”, no login prompt.
- [ ] `/metrics` contains `demo_http_requests_total`, `demo_queue_depth`,
      `demo_failed_jobs` and `demo_queue_oldest_job_age_seconds`.
- [ ] Dispatch `/jobs` once and find `POST jobs/dispatch` in Jaeger: the `send`
      producer and the `process` consumer spans share one trace id.
- [ ] Create 10 orders on `/orders` and check the **Orders** card on Pulse. If it
      is empty, `docker compose ps pulse` and `php artisan config:clear`.
- [ ] Nightwatch agent container is up and the dashboard loads. It needs
      internet; everything else in the stack is local.
- [ ] Run the whole demo once, start to finish, on the projector resolution.
- [ ] Turn off notifications. Close Slack. Mute the laptop.
- [ ] Have a phone hotspot ready in case conference Wi-Fi dies. Nightwatch is
      the only thing that truly needs internet.
- [ ] Terminal font size bumped up so the back row can read the `artisan`
      commands.
- [ ] Slides advanced by keyboard, never by trackpad. Trackpads double-fire.
- [ ] Reset the demo between rehearsals: `/orders` reset button, clear the log,
      then generate one fresh order and one fresh `/slow` request.

---

# Section 1 — Cold open: “It works on my machine”

**Time:** 3 minutes. **Key message:** logs alone tell you *that* something
broke, never *where the time went*.

- A request takes 4 seconds in production; 90 ms on your laptop.
- You `grep` the log. It says “Request completed.” No clue why.
- You have logs. You do not have *answers*.

**On screen:** a 12,000-line `laravel.log` next to a stopwatch. Keep it up.

**Demo:** none. **If it breaks:** nothing to break; this survives on voice.

---

# Section 2 — The three shapes of telemetry

**Time:** 5 minutes. **Key message:** logs, metrics and traces answer three
different questions, and you need all three.

- **Logs** — *what* happened. Many, detailed, expensive to search.
- **Metrics** — *how much / how fast*, over time. Cheap, adds up, loses detail.
- **Traces** — *where the time went*, for one request. The missing middle.
- A **trace** is one request. A **span** is one timed step inside it.

**On screen:** three columns, then the hand-drawn trace: a wide `GET /checkout`
bar with four thinner children (SQL, cURL, render, cache).

**Demo:** one reveal click. **If it breaks:** the static three-column slide.

## New bridge slide — “Tre segnali, tre strumenti (e un tavolo)” (30 s)

This is the slide that reconciles the abstract with the deck. Say it explicitly:

- The abstract promises three **signals**: logs, metrics, traces.
- The talk looks at them through three **lenses**: Pulse, Nightwatch, OTel.
- The tools are not one per signal: each reads more than one, so there is no
  Pulse-to-logs / Nightwatch-to-metrics pairing on the slide.
- It is not all OpenTelemetry: logs are Laravel logs + trace id, metrics are
  Prometheus (pull), traces are OTel + Jaeger.
- **Grafana is not a fourth lens.** It is the table the data sits on; it stores
  nothing.

If you skip this slide, an attendee who read the abstract will spend the next
20 minutes wondering why the talk is organised by tools.

---

# Section 3 — Laravel's observability ladder

**Time:** 5 minutes. **Key message:** four tools, not rivals; a ladder of
increasing effort and power.

- **Log + Telescope** — free, local, one request in detail.
- **Pulse** — in-app dashboards: throughput, slow queries, slow jobs.
- **Nightwatch** — hosted, production, Laravel-native, one install.
- **OpenTelemetry** — open standard, portable, traces across services.

**On screen:** four rungs, cheap at the bottom.

**Timing:** ~55 seconds per rung. At 13 minutes you are on track; at 16, skip
the Pulse card and go straight to the dashboard.

# Section 4 — The demo app tour

**Time:** 3 minutes. **Key message:** one small app, seven deliberately shaped
behaviours, so every lens has something to reveal.

- `/fast` — cached; the boring baseline.
- `/slow` — N+1 + slow external HTTP call.
- `/fail` — throws on purpose.
- `/jobs` — a chain of 3 and a batch of 8, with retries.
- `/cache` — a miss, then a hit.
- `demo:heartbeat` — scheduled task, nobody waiting.
- `/chaos` — slow network, slow database, job failure rate.

**Demo:** home page + `/chaos` tab. **If it breaks:** cut to `/slow` + `/chaos`.

---

## Quick Telescope demo — “Telescope, trenta secondi” (30-40 s)

After the tour, show the cheapest rung live before moving to Pulse.

1. Open http://127.0.0.1:8000/telescope.
2. Generate a fresh request at http://127.0.0.1:8000/demo/telescope.
3. Back in Telescope, open **Requests** → the latest `GET /demo/telescope`, then
   show only **Queries** and **Logs**.

Close by saying it is a development microscope: entries live in the local
`telescope_entries` table and it is switched off in production. If the browser
does not cooperate, go straight to Pulse; the demo is not the slide.

---

# Section 5 — Lens 1: Pulse (in-app)

**Time:** 6 minutes. **Key message:** cheap, always-on numbers *inside* your
app; it is a small pipeline you can extend, not a black box.

The section has three beats, roughly 2 + 2 + 2 minutes:

1. **What it shows** (2 min) — the dashboard, the slow-query and slow-job cards.
2. **How it works** (2 min) — recorder → buffer → ingest → storage, and the
   `pulse:check` heartbeat.
3. **A custom card** (2 min) — the Orders card, the code behind it, and the
   `/orders` click that moves Pulse, Prometheus and Grafana together.

- Runs in the app. No extra infrastructure, no data leaving the server.
- Cards: requests, slow queries, slow jobs, cache hit rate, exceptions, plus
  the demo's own **Orders** card.
- **Aggregated** — great for trends, no per-request story.
- **Trend, not alert** — Pulse tells you p95 is rising; the threshold that
  wakes you up is yours to set (Nightwatch/Grafana).

## How it works — say this, in this order

- A **recorder** is a class in `config/pulse.php`. It listens to a Laravel event
  and calls `Pulse::record(...)`, declaring the aggregates it wants:
  `->count()`, `->sum()`, `->max()`, `->avg()`.
- `record()` does **not** write to the database. Entries sit in an **in-memory
  buffer** and are flushed at the end of the request/command, or when the buffer
  passes 5,000 entries. That is why Pulse does not slow the request down.
- The **ingest driver** decides where the buffer goes: `storage` writes
  directly (this demo), `redis` publishes to a stream consumed by `pulse:work`.
- **Storage** is three tables with three jobs: `pulse_entries` (raw events),
  `pulse_aggregates` (count/min/max/sum/avg buckets for 1 h, 6 h, 24 h, 7 days),
  `pulse_values` (latest value per key, e.g. the last order or server CPU).
- **The timer:** `pulse:check` is a long-running process, not a cron. Every
  second it dispatches a `SharedBeat`, flushes the buffer, and the `Servers`
  recorder samples CPU/memory every 15 s. `pulse:check --once` can be scheduled
  if you do not want a permanent process, at the cost of granularity.
- A **scheduled command** can also call `Pulse::record()` every N minutes. That
  is the pattern for a business KPI no framework event produces: the scheduler
  is your timer, Pulse is only the storage and the card.
- The dashboard cards are **Livewire components** that read aggregates through
  `aggregateTotal()`/`values()` and poll every 5 s. They never query the raw
  tables directly.

## The custom card (the demo that proves it is extensible)

- Files: `app/Events/OrderPlaced.php`, `app/Pulse/Recorders/Orders.php`,
  `app/Livewire/Pulse/OrdersCard.php`,
  `resources/views/pulse/orders-card.blade.php`, plus one line in
  `resources/views/vendor/pulse/dashboard.blade.php`.
- The recorder turns one event into a count and a revenue sum; the card reads
  them back. The `/orders` page dispatches the event **and** updates the
  Prometheus counter, so one click moves all three lenses.
- **On screen:** `/orders`, create 10 orders, then back to Pulse. The Orders card
  goes from empty to 10 orders and a revenue figure. Wait one 5 s poll.
- **If it breaks:** the card is a normal Livewire component. Show the code
  slide and say “this is the whole thing”; do not debug the database on stage.
  If the numbers are stale, the card cache is 5 s — reload once.

**Abstract tie-back:** “degradation before users notice” is the trend plus a
threshold you chose. Do not let the audience think Pulse alerts by itself.

**If it breaks:** screenshot slide; do not restart containers on stage.

---

# Section 6 — Lens 2: Nightwatch (hosted)

**Time:** 4 minutes. **Key message:** traces and errors with almost no setup,
because Laravel runs the backend.

- One Composer package. No collector *to manage*, no backend, no infrastructure.
  (The agent is still a local collector — theirs, on `127.0.0.1:2407`; covered two
  slides later, so don't let the line stand alone.)
- Hosted: telemetry leaves your servers and lives on Laravel's.
- Trade-off: less effort, less control.
- It is a **superset of Pulse**: requests, jobs, commands, scheduled tasks,
  exceptions, mail, notifications, deploy metadata, and the **source code** of
  the failing line.

**New failed-job callout:** the abstract opens with “a background job fails
without leaving a trace.” Answer it on this slide: `failed_jobs` lists it,
Pulse counts it, Nightwatch groups the exception, and the log has the `trace_id`
to walk back to the request that dispatched it.

**Demo:** `/nightwatch`, three buttons. Show **one** error; mail and
notifications only if you have time.

**If it breaks:** it needs internet. Say the conference Wi-Fi just made the
hosted-vs-local comparison for you, then use the screenshots. Do not insist.

---

# Section 7 — Lens 3: OpenTelemetry + Jaeger (the waterfall)

**Time:** 6 minutes. **Protect it.** **Key message:** traces show the *shape*
of a slow request — which span, how long, who is to blame.

- **OTel** produces; **OTLP** transports; a **collector** forwards; the
  **backend** stores. Here the backend is Jaeger — and there is **no separate
  collector**: Jaeger all-in-one has its own OTLP receiver on 4318.
- Span anatomy: `trace_id`, `span_id`, `parent_span_id`. Context links children
  automatically; a span is immutable once it ends.
- Auto-instrumentation gives you queries, cache, HTTP client, queue and views.
- It is a standard: change the endpoint, the telemetry goes elsewhere without
  touching code.

**Order matters:** OTel intro → span anatomy → *then* the waterfall. The ids
come first, the picture second; the waterfall slide opens with “the same spans,
rendered by the backend.”

**Demo (money moment):** `/slow` waterfall — the N+1 bars, then the long
external call. Use `/chaos` to add latency to the external service, re-run, and
show the shape change: same SQL, the external bar ate the request.

**If it breaks:** wait 15 seconds for the batch; do not spam refresh. If Jaeger
is down, use the pre-recorded 20-second clip and say “here is one I recorded
earlier.”

---

# Section 8 — Inside OpenTelemetry (and Grafana)

**Time:** 4 minutes. **This is the first section to cut.** Keep OTLP, cost and
data sovereignty; compress the buffer, sampler and worker-mode details.

- OTLP in two flavours: gRPC/protobuf over **HTTP/2** (4317) vs OTLP/HTTP over
  **HTTP/1.1** or 2, protobuf **or JSON** (4318). The demo uses http/protobuf.
- OTLP payload: 3,493 bytes, 10 spans, one POST to port 4318.
- **Edge case — “La stessa traccia attraversa la coda” (45 s):** open `/jobs`,
  dispatch, then in Jaeger search `POST jobs/dispatch` and open the latest trace.
  The `send` producer span is created inside the HTTP request; the `process`
  consumer span is created in the worker, minutes later, with the same `trace_id`.
  The `traceparent` travels in the job payload. Frame it as the edge case: we
  have left the HTTP request. A job started from CLI or the scheduler has no
  request to propagate from, so it opens a new trace — the right question to ask,
  not a bug.
- The batch processor: 2048 / 512 / 5 s / 30 s defaults, but in PHP the process
  dies and the buffer flushes at shutdown — ~0.6 s in this demo, not 5 s. It
  lands right after the queue beat, where long-running workers make it matter.
- Cost: sampling, head-based sampling, what it does to the bill.
- Data sovereignty: what leaves your server and what does not — telemetry vs the
  `traceparent` header (W3C Trace Context, linked on the slide).

**Metrics, push vs pull:** OTel pushes; Prometheus pulls. A PHP process lives
for one request, so the Prometheus client keeps counters in Postgres; OTel
metrics need a long-lived process (Octane or a queue worker). This is why the
demo uses Prometheus for metrics.

## New beat — “Grafana non è un quarto strumento” (60–90 s)

Open `localhost:3000`; it is the home dashboard. Show:

- orders and revenue per minute, p50/p95 order value;
- HTTP requests per minute and p95 latency;
- queue depth, failed jobs, age of the oldest waiting job;
- **Explore → Jaeger** for the same trace, without changing window.

The line to land: **Grafana is a query layer, not a backend.** It stores
nothing. Change the datasource and the panels stay.

**If it breaks:** everything here is local Docker; no internet needed. Use the
screenshot if the browser misbehaves.

---

# Section 9 — Three lenses, two axes

**Time:** 3 minutes. **Key message:** the choice is not “which tool is best”,
it is three questions.

- Horizontal axis: aggregate vs per-request.
- Vertical axis: you manage it vs someone else does.
- Pulse top-left; OTel + Jaeger top-right; Nightwatch bottom-right; commercial
  vendors (Datadog, New Relic, Sentry) bottom-left.
- Three questions: must the data stay with you? do you have non-Laravel
  services? is someone willing to run a collector?

**Do not add new content here.** Let the map land; pause after the three
questions.

---

# Section 10 — Monday morning + closing log line

**Time:** 2 minutes. **Key message:** start at the bottom rung; climb only when
a real question pushes you up.

- Tonight: Telescope in your local app. Fifteen minutes.
- This week: Pulse in staging. Find the slowest job.
- When needed: Horizon if queues become your world; Nightwatch if you want
  traces without infrastructure; OTel if you need the standard and want to own
  the data.

**Close with “La stessa riga di log”:** the same line from the cold open, now
next to the waterfall. Logs say *that*. Metrics say *how much*. Traces say
*where*.

Then pause and stop. Do not add anything after the log line.

---

# Appendix — only if asked

Keep these out of the 40 minutes. They are answers, not slides to rush through.
The full versions, plus the Q&A and the Grafana/vendor notes, are in
[`EXTRA.md`](../EXTRA.md). The deck itself now ends on “Grazie” and the glossary.

- **SQL Commenter** — the ORM appends the trace context to the SQL text, so the
  database sees the `trace_id` too.
- **One backend for three signals** — Grafana Cloud and SigNoz accept OTLP for
  logs, metrics and traces. Composable (Loki/Tempo/Mimir) vs monolithic.
- **Monitor the gaps** — sampling, dropped spans, scrape health, queue age,
  missing logs.
- **Error tracking and APM** — where Nightwatch stops and Datadog/Sentry begin.
- **Laravel things** — Pail, Octane, the `Context` facade, Pulse vs Horizon.

---

## Demo run order (for rehearsal)

1. Cold open — no demo.
2. `/fast` — baseline.
3. `/slow` — N+1 + external call.
4. `/fail` — 500; the HTTP metrics record it.
5. `/jobs` — chain + batch.
6. `/cache` — miss, then hit.
7. `demo:heartbeat` — scheduled task.
8. Pulse — slow queries, slow jobs, then `/orders` + the custom Orders card.
9. Nightwatch — one error; failed-job callout.
10. Jaeger — `/slow` waterfall.
11. `/chaos` — external latency; re-run the waterfall.
12. `/jobs` + Jaeger — the trace crosses the queue.
13. `/orders` — create orders; Prometheus windows.
14. Grafana — orders, HTTP, queue panels; Explore → Jaeger.
15. `/logs` — trace id on and off; the same log line.

---

## If everything breaks

- **App down:** show the README and the slides; do not debug on stage for more
  than 30 seconds.
- **Jaeger down:** pre-recorded 20-second waterfall; say so.
- **Grafana down:** screenshot of the dashboard; everything is local, so this
  is unlikely.
- **Prometheus down:** `/metrics` raw exposition and the Grafana screenshot.
- **Nightwatch down:** the internet is the dependency. Say it, use screenshots.
- **Queue worker down:** the job stays in `jobs`; show `demo_queue_depth` and
  `demo_queue_oldest_job_age_seconds`, explain, move on.
- **Projector dies:** sections 1, 2, 5, 6 and 10 survive on voice alone.
