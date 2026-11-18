@extends('layouts.app')

@section('title', 'Last outbound traceparent')

@section('content')
    <h1>Last outbound traceparent</h1>
    <p class="lede">
        Every outgoing HTTP request carries a <code>traceparent</code> header, injected by OpenTelemetry's client
        instrumentation. That is how a service you call joins the same trace. This page shows the last one this
        app sent, and where it went.
    </p>

    @if ($traceparent)
        @php $parts = explode('-', $traceparent); @endphp

        <div class="card">
            <h3>The header that left the app</h3>
            <p style="margin: 0 0 0.4rem;">
                <code style="font-size: 1rem; word-break: break-all;">{{ $traceparent }}</code>
            </p>
            <p style="color: var(--muted); font-size: 0.85rem; margin: 0;">
                sent to <code>{{ $outboundUrl }}</code>@if ($recordedAt) at <code>{{ $recordedAt }}</code>@endif
            </p>
        </div>

        @if (count($parts) === 4)
            <h2>What is in it</h2>
            <div class="card">
                <table>
                    <thead>
                        <tr><th>Part</th><th>Value</th><th>Meaning</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>version</td>
                            <td class="mono">{{ $parts[0] }}</td>
                            <td>W3C Trace Context format</td>
                        </tr>
                        <tr>
                            <td>trace-id</td>
                            <td class="mono">{{ $parts[1] }}</td>
                            <td><b>the trace this request belongs to</b> — the same id you search in Jaeger</td>
                        </tr>
                        <tr>
                            <td>parent-id</td>
                            <td class="mono">{{ $parts[2] }}</td>
                            <td>the client span for this specific call</td>
                        </tr>
                        <tr>
                            <td>flags</td>
                            <td class="mono">{{ $parts[3] }}</td>
                            <td>{{ $parts[3] === '01' ? 'sampled — this trace was kept' : 'not sampled' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h2>Follow it into Jaeger</h2>
            <div class="card">
                <p>
                    The trace id in the middle of that header is the one to open. The link below goes straight to
                    the trace — no searching, no pasting.
                </p>
                <div class="row" style="gap: 0.5rem; align-items: center;">
                    <a class="btn primary" href="{{ $jaegerUrl }}/trace/{{ $parts[1] }}" target="_blank" rel="noreferrer">Open this trace in Jaeger</a>
                    <code>{{ $parts[1] }}</code>
                </div>
            </div>
        @endif
    @else
        <div class="card">
            <h3>Nothing recorded yet</h3>
            <p>
                No outgoing request has been traced since the app started. Call
                <a href="{{ route('demo.slow') }}">/slow</a> — it calls
                <code>{{ config('telemetry-demo.external.base_url') }}</code> — then come back.
            </p>
            <a class="btn primary" href="{{ route('demo.slow') }}">Call /slow</a>
        </div>
    @endif

    @if ($currentTraceId)
        <p style="color: var(--muted); font-size: 0.85rem; margin-top: 2rem;">
            This page is traced as well: its own trace id is <code>{{ $currentTraceId }}</code>. Note that it is a
            different id — the header above belongs to the request that called the external service.
        </p>
    @endif
@endsection