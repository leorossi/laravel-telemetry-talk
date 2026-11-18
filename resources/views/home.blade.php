@extends('layouts.app')

@section('title', 'Telemetry demo — the seven hooks')

@section('content')
    <h1>One app, seven deliberately shaped behaviours</h1>
    <p class="lede">
        Everything the talk demonstrates starts here. Every lens — Pulse, OpenTelemetry/Jaeger and Nightwatch —
        is looking at this same application.
    </p>

    <div class="grid">
        @foreach ($endpoints as $endpoint)
            <div class="card">
                <h3>{{ $endpoint['title'] }}</h3>
                <p>{{ $endpoint['note'] }}</p>
                <div class="row" style="gap: 0.4rem; align-items: center;">
                    <a class="btn primary" href="{{ $endpoint['url'] }}">Open</a>
                    <button type="button" class="btn tiny" data-burst-url="{{ $endpoint['url'] }}" data-burst-count="5">&times;5</button>
                    <code>{{ $endpoint['method'] }}</code>
                </div>
            </div>
        @endforeach
    </div>

    <h2>Where to look</h2>
    <div class="grid">
        <div class="card">
            <h3>Pulse</h3>
            <p>In-app dashboards: requests, slow queries, slow jobs, cache, exceptions.</p>
            <a class="btn" href="{{ $pulseUrl }}" target="_blank" rel="noreferrer">{{ $pulseUrl }}</a>
        </div>
        <div class="card">
            <h3>Telescope</h3>
            <p>Dev-only microscope: every query, cache access and log line of one request, stored in your local database.</p>
            <a class="btn" href="{{ url('/telescope') }}" target="_blank" rel="noreferrer">Open Telescope</a>
            <a class="btn tiny" href="{{ route('demo.telescope') }}" target="_blank" rel="noreferrer">Generate a request</a>
        </div>
        <div class="card">
            <h3>Jaeger</h3>
            <p>OpenTelemetry traces exported over OTLP. Service name <code>{{ config('opentelemetry.service_name') }}</code>.</p>
            <a class="btn" href="{{ $jaegerUrl }}" target="_blank" rel="noreferrer">{{ $jaegerUrl }}</a>
        </div>
        <div class="card">
            <h3>Grafana</h3>
            <p>One pane over the same data: the telemetry dashboard reads Prometheus, and Jaeger is a datasource too. Grafana stores nothing itself.</p>
            <a class="btn" href="{{ $grafanaDashboardUrl }}" target="_blank" rel="noreferrer">Open the telemetry dashboard</a>
            <a class="btn tiny" href="{{ $grafanaUrl }}" target="_blank" rel="noreferrer">Grafana</a>
        </div>
        <div class="card">
            <h3>Nightwatch</h3>
            @if (config('nightwatch.enabled') && config('nightwatch.token'))
                <p><span class="pill good">enabled</span> Sending to Laravel's ingest.</p>
            @else
                <p><span class="pill">off</span> Set <code>NIGHTWATCH_TOKEN</code> and <code>NIGHTWATCH_ENABLED=true</code>, then run <code>php artisan nightwatch:agent</code>.</p>
            @endif
            <a class="btn" href="{{ route('demo.nightwatch') }}">Open /nightwatch</a>
            <a class="btn tiny" href="https://nightwatch.laravel.com/dashboard" target="_blank" rel="noreferrer">Nightwatch dashboard</a>
        </div>
    </div>

    <h2>Live state</h2>
    <div class="grid">
        <div class="card">
            <h3>Chaos switches</h3>
            @if ($chaos === [])
                <p>All defaults. Nothing is being faked.</p>
            @else
                <p>
                    @foreach ($chaos as $key => $value)
                        <span class="pill warn">{{ $key }} = {{ is_bool($value) ? ($value ? 'on' : 'off') : $value }}</span>
                    @endforeach
                </p>
            @endif
            <a class="btn" href="{{ route('demo.chaos') }}">Open the chaos panel</a>
        </div>
        <div class="card">
            <h3>Scheduled task</h3>
            @if ($heartbeat)
                <p>
                    <code>demo:heartbeat</code> last ran at <code>{{ $heartbeat['ran_at'] }}</code>:
                    {{ $heartbeat['orders'] }} orders, {{ $heartbeat['items'] }} items,
                    {{ $heartbeat['customers'] }} customers.
                </p>
            @else
                <p>No heartbeat yet. Run <code>php artisan schedule:work</code>.</p>
            @endif
        </div>
        <div class="card">
            <h3>External dependency</h3>
            <p>The slow endpoint and the queue jobs call <code>{{ $externalHost }}</code>. It is stubbed in-process, so no internet is required.</p>
        </div>
        <div class="card">
            <h3>Outbound traceparent</h3>
            <p>The trace context header the app injects into outgoing HTTP calls — the same <code>trace_id</code> you look for in Jaeger.</p>
            <a class="btn" href="{{ route('demo.traceparent') }}">Open /traceparent</a>
        </div>
        <div class="card">
            <h3>Logs and traces</h3>
            <p>How a log line gets its <code>trace_id</code> — and what happens when it doesn't. Switchable.</p>
            <a class="btn" href="{{ route('demo.logs') }}">Open /logs</a>
        </div>
        <div class="card">
            <h3>Orders and Prometheus</h3>
            <p>Create a random order, record it as a Prometheus metric, and read the revenue back over the last minutes.</p>
            <a class="btn" href="{{ route('demo.orders') }}">Open /orders</a>
        </div>
        <div class="card">
            <h3>Nightwatch</h3>
            <p>Mail, notifications, and an error that does not break the page — the three things only Nightwatch sees.</p>
            <a class="btn" href="{{ route('demo.nightwatch') }}">Open /nightwatch</a>
            <a class="btn tiny" href="https://nightwatch.laravel.com/dashboard" target="_blank" rel="noreferrer">Nightwatch dashboard</a>
        </div>
    </div>
@endsection
