# Troubleshooting

[← README](../README.md)

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
