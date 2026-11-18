@inject('metrics', 'App\Support\RequestMetrics')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Telemetry demo')</title>
    <style>
        :root {
            --bg: #0f1115;
            --panel: #171b23;
            --panel-2: #1e232d;
            --line: #2a3140;
            --text: #e7eaf0;
            --muted: #98a2b3;
            --accent: #ff2d20;
            --good: #3ddc97;
            --warn: #ffb020;
            --bad: #ff5c5c;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0 0 5rem;
            background: var(--bg);
            color: var(--text);
            font: 16px/1.55 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        a { color: inherit; }

        header {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--line);
            background: #12151b;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .brand { font-weight: 700; letter-spacing: -0.01em; text-decoration: none; }
        .brand span { color: var(--accent); }

        nav { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; }
        nav .nav-group { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; }

        nav a {
            padding: 0.35rem 0.7rem;
            border: 1px solid var(--line);
            border-radius: 999px;
            font-size: 0.85rem;
            text-decoration: none;
            color: var(--muted);
        }

        nav a:hover, nav a.active { color: var(--text); border-color: #48536a; background: var(--panel-2); }

        nav .nav-sep { width: 1px; height: 1.15rem; background: var(--line); margin: 0 0.45rem; }

        /* External services: dashed accent pill with an outbound arrow to set them
           apart from the app's own endpoints. */
        nav a.ext {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            color: #ff9c94;
            border-style: dashed;
            border-color: #5a2a26;
            background: #1d1417;
        }

        nav a.ext:hover, nav a.ext.active {
            color: #fff;
            border-color: var(--accent);
            background: #331612;
        }

        nav a.ext svg { width: 0.7em; height: 0.7em; opacity: 0.85; }

        main { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; }

        h1 { font-size: 2rem; margin: 0 0 0.25rem; letter-spacing: -0.02em; }
        h2 { font-size: 1.15rem; margin: 2.5rem 0 0.75rem; letter-spacing: -0.01em; }
        p.lede { color: var(--muted); margin: 0 0 1.5rem; max-width: 70ch; }

        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }

        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 1.1rem 1.2rem;
        }

        .card h3 { margin: 0 0 0.35rem; font-size: 1.05rem; }
        .card p { margin: 0 0 0.9rem; color: var(--muted); font-size: 0.92rem; }

        .stat { font-variant-numeric: tabular-nums; font-size: 1.6rem; font-weight: 600; }
        .stat small { display: block; font-size: 0.8rem; font-weight: 400; color: var(--muted); }

        .row { display: flex; flex-wrap: wrap; gap: 1.5rem; }

        .mono, code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        code { font-size: 0.88em; color: #c9d1e4; }

        .btn {
            display: inline-block;
            padding: 0.55rem 1rem;
            border-radius: 9px;
            border: 1px solid var(--line);
            background: var(--panel-2);
            color: var(--text);
            font: inherit;
            font-size: 0.92rem;
            text-decoration: none;
            cursor: pointer;
        }

        .btn:hover { border-color: #5a6684; }
        .btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; }
        .btn.on { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; }
        .btn.tiny { padding: 0.3rem 0.6rem; font-size: 0.8rem; }

        .flash {
            padding: 0.8rem 1rem;
            margin-bottom: 1.5rem;
            border-radius: 10px;
            border: 1px solid #2f4a3c;
            background: #16241d;
            color: var(--good);
            font-size: 0.92rem;
        }

        .banner {
            padding: 0.8rem 1rem;
            margin-bottom: 1.5rem;
            border-radius: 10px;
            border: 1px solid #5a3131;
            background: #251618;
            color: #ffb3b3;
            font-size: 0.92rem;
        }

        .pill {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            font-size: 0.78rem;
            border: 1px solid var(--line);
            color: var(--muted);
        }

        .pill.good { color: var(--good); border-color: #2f4a3c; }
        .pill.bad { color: var(--bad); border-color: #5a3131; }
        .pill.warn { color: var(--warn); border-color: #5a4a24; }

        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 0.55rem 0.6rem; border-bottom: 1px solid var(--line); }
        th { color: var(--muted); font-weight: 500; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }

        .bars { display: flex; align-items: flex-end; gap: 2px; height: 80px; margin-top: 0.5rem; }
        .bars span { flex: 1; background: linear-gradient(180deg, var(--accent), #7a150c); border-radius: 2px 2px 0 0; min-height: 2px; }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            padding: 0.5rem;
            border-top: 1px solid var(--line);
            background: #12151b;
            color: var(--muted);
            font-size: 0.82rem;
            font-variant-numeric: tabular-nums;
        }

        footer b { color: var(--text); font-weight: 600; }
    </style>
</head>
<body>
<header>
    <a class="brand" href="{{ route('home') }}">laravel<span>·</span>telemetry demo</a>
    <nav>
        <div class="nav-group">
            <a href="{{ route('demo.fast') }}" @class(['active' => request()->routeIs('demo.fast')])>/fast</a>
            <a href="{{ route('demo.slow') }}" @class(['active' => request()->routeIs('demo.slow')])>/slow</a>
            <a href="{{ route('demo.cache') }}" @class(['active' => request()->routeIs('demo.cache')])>/cache</a>
            <a href="{{ route('demo.jobs') }}" @class(['active' => request()->routeIs('demo.jobs')])>/jobs</a>
            <a href="{{ route('demo.fail') }}" @class(['active' => request()->routeIs('demo.fail')])>/fail</a>
            <a href="{{ route('demo.chaos') }}" @class(['active' => request()->routeIs('demo.chaos')])>/chaos</a>
            <a href="{{ route('demo.traceparent') }}" @class(['active' => request()->routeIs('demo.traceparent')])>/traceparent</a>
            <a href="{{ route('demo.logs') }}" @class(['active' => request()->routeIs('demo.logs')])>/logs</a>
            <a href="{{ route('demo.telescope') }}" @class(['active' => request()->routeIs('demo.telescope')])>/demo/telescope</a>
            <a href="{{ route('demo.orders') }}" @class(['active' => request()->routeIs('demo.orders')])>/orders</a>
            <a href="{{ route('demo.nightwatch') }}" @class(['active' => request()->routeIs('demo.nightwatch')])>/nightwatch</a>
        </div>

        <span class="nav-sep" aria-hidden="true"></span>

        <div class="nav-group">
            <a class="ext" href="/pulse" target="_blank" rel="noreferrer" @class(['active' => request()->is('pulse*')])>pulse
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>
                </svg>
            </a>
            <a class="ext" href="/telescope" target="_blank" rel="noreferrer" @class(['active' => request()->is('telescope*')])>telescope
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>
                </svg>
            </a>
            <a class="ext" href="https://nightwatch.laravel.com/dashboard" target="_blank" rel="noreferrer">nightwatch
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>
                </svg>
            </a>
            <a class="ext" href="http://localhost:9090" target="_blank" rel="noreferrer">prometheus
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>
                </svg>
            </a>
            <a class="ext" href="http://localhost:16686" target="_blank" rel="noreferrer">jaeger
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>
                </svg>
            </a>
            <a class="ext" href="{{ config('telemetry-demo.grafana.public_url') }}" target="_blank" rel="noreferrer">grafana
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>
                </svg>
            </a>
        </div>
    </nav>
</header>

<main>
    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>

<footer>
    <span>queries <b>{{ $metrics->queries() }}</b></span>
    <span>elapsed <b>{{ $metrics->durationMs() }} ms</b></span>
    <span>env <b>{{ app()->environment() }}</b></span>
</footer>

<script>
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-burst-url]');
    if (!button) return;

    const label = button.textContent;
    const count = Number(button.dataset.burstCount || 5);
    button.disabled = true;

    for (let index = 1; index <= count; index++) {
        button.textContent = `${index}/${count}`;
        await fetch(button.dataset.burstUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    }

    button.textContent = 'done';
    setTimeout(() => { button.textContent = label; button.disabled = false; }, 1200);
});
</script>
</body>
</html>
