# Laravel al microscopio

**Dove sono finiti i quattro secondi?** Talk e app demo per **LaravelDay 2026** (18 novembre, 15:10–15:50).

Una piccola app Laravel 12, sette comportamenti costruiti apposta e tre lenti sulla stessa richiesta:
**Pulse**, **Nightwatch** e **OpenTelemetry + Jaeger**. Tutto gira in Docker, senza account né internet
(Nightwatch a parte, vedi sotto).

## Quick start

```bash
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

| Servizio | URL |
|---|---|
| App | <http://127.0.0.1:8000> |
| Jaeger | <http://localhost:16686> |
| Prometheus | <http://localhost:9090> |
| Grafana | <http://localhost:3000> |

Test e formattazione: `docker compose exec app php artisan test` · `docker compose exec app vendor/bin/pint`

## Documentazione

| Argomento | File |
|---|---|
| Servizi Docker e container dell'app | [docs/stack.md](docs/stack.md) |
| Avvio, processi long-running, log, editing del codice | [docs/running.md](docs/running.md) |
| I sette comportamenti e il pannello chaos | [docs/hooks.md](docs/hooks.md) |
| Jaeger, log, metriche, Grafana, Pulse | [docs/observability.md](docs/observability.md) |
| Configurare Nightwatch | [docs/nightwatch.md](docs/nightwatch.md) |
| Wiring dei pacchetti e classi di supporto | [docs/wiring.md](docs/wiring.md) |
| Problemi comuni | [docs/troubleshooting.md](docs/troubleshooting.md) |
| Run-of-show e tempi del talk | [docs/outline.md](docs/outline.md) |
| Q&A e materiale extra | [EXTRA.md](EXTRA.md) |

## Slide

PDF: [dark](slides/slides-export.pdf) · [light](slides/slides-export-light.pdf)
