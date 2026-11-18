# Stack e container

[← README](../README.md)

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
