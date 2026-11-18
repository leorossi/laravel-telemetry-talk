# Avvio e uso quotidiano

[← README](../README.md)

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
