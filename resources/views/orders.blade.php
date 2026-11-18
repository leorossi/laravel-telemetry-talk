@extends('layouts.app')

@section('title', 'Orders and Prometheus')

@section('content')
    <h1>Orders &rarr; Prometheus</h1>
    <p class="lede">
        Each click creates a random order and records it as a metric. The counters live in Postgres — the client keeps
        them there because a PHP process only lives for one request — and Prometheus scrapes them from
        <code>/metrics</code> every 5 seconds. Nothing is pushed.
        The same numbers are on the provisioned
        <a href="{{ config('telemetry-demo.grafana.dashboard_url') }}" target="_blank" rel="noreferrer">Grafana dashboard</a>,
        which queries Prometheus and stores nothing itself.
    </p>

    <h2>Create an order</h2>
    <div class="card">
        <form method="POST" action="{{ route('demo.orders.store') }}" class="row" style="gap: 0.6rem; align-items: center;">
            @csrf
            <button type="submit" class="btn primary">Create a random order</button>
            <button type="submit" class="btn" name="burst" value="5">Create 5</button>
        </form>
        <p style="margin: 0.9rem 0 0; color: var(--muted); font-size: 0.9rem;">
            <b>{{ $totalOrders }}</b> orders in the database, <b>€{{ number_format($totalRevenueCents / 100, 2) }}</b> in total.
        </p>
    </div>

    <h2>What Prometheus knows <span class="pill">read back from Prometheus</span></h2>
    <div class="grid">
        @foreach ($windows as $window)
            <div class="card">
                <h3>last {{ $window['minutes'] }} minute{{ $window['minutes'] === 1 ? '' : 's' }}</h3>

                <div class="stat">
                    @if ($window['revenue'] === null)
                        &mdash;
                        <small>no data yet</small>
                    @else
                        €{{ number_format($window['revenue'], 2) }}
                        <small>revenue</small>
                    @endif
                </div>

                <p style="color: var(--muted); font-size: 0.9rem;">
                    @if ($window['orders'] === null)
                        &mdash;
                    @else
                        {{ (int) round($window['orders']) }} order{{ (int) round($window['orders']) === 1 ? '' : 's' }}
                    @endif
                </p>

                <a class="btn tiny" href="{{ $window['url'] }}" target="_blank" rel="noreferrer">Open in Prometheus</a>
            </div>
        @endforeach
    </div>

    <h2>The queries behind those numbers</h2>
    <div class="card">
        <p>
            "Revenue in the last N minutes" is not a stored value. The counter only ever goes up; the window is
            applied at query time, and Prometheus works it out from the scrapes it kept.
        </p>
        <pre class="mono" style="font-size: 0.85rem; overflow-x: auto;"><code># revenue in the last 10 minutes, in euros
sum(increase(demo_order_revenue_cents_total[10m])) / 100

# how many orders in the last 10 minutes
sum(increase(demo_orders_created_total[10m]))

# average order value, from the histogram
sum(rate(demo_order_value_cents_sum[5m])) / sum(rate(demo_order_value_cents_count[5m]))</code></pre>
        <p style="color: var(--muted); font-size: 0.88rem; margin-bottom: 0;">
            One honest caveat: <code>increase()</code> <b>extrapolates</b> from the samples it has, so a single burst
            that lands inside one scrape interval reads a little high — spread the clicks out and it settles. If you
            want the exact difference instead, and Prometheus has been running longer than the window:
            <code>sum(demo_orders_created_total) - sum(demo_orders_created_total offset 10m)</code>.
        </p>
    </div>

    <h2>Two different worlds</h2>
    <div class="grid">
        <div class="card">
            <h3>OpenTelemetry</h3>
            <p>
                The app <b>pushes</b>. It produces telemetry and ships it to a collector over OTLP. Traces and logs
                work well in PHP; metrics are the awkward one, because an aggregate needs something that outlives a
                single request.
            </p>
        </div>
        <div class="card">
            <h3>Prometheus</h3>
            <p>
                Prometheus <b>pulls</b>. The app just exposes <code>/metrics</code> and Prometheus comes to collect it.
                That is why the counters have to live in shared storage — they must survive between requests.
            </p>
        </div>
    </div>

    <p style="color: var(--muted); font-size: 0.85rem; margin-top: 1.5rem;">
        Raw exposition format: <a href="{{ route('demo.metrics') }}">{{ route('demo.metrics') }}</a>.
        In Prometheus itself: <a href="{{ $prometheusUrl }}" target="_blank" rel="noreferrer">{{ $prometheusUrl }}</a>.
        In Grafana: <a href="{{ config('telemetry-demo.grafana.dashboard_url') }}" target="_blank" rel="noreferrer">the telemetry dashboard</a>
        (datasources: Prometheus and Jaeger).
    </p>

    <h2>Reset, between rehearsals</h2>
    <div class="card">
        <p>
            Deletes the orders this page created, wipes the counters, and asks Prometheus to forget their history — so
            the next run starts from zero instead of from your rehearsal. The seeded data is untouched.
        </p>
        <form method="POST" action="{{ route('demo.orders.reset') }}">
            @csrf
            <button type="submit" class="btn">Reset the demo</button>
        </form>
    </div>
@endsection
