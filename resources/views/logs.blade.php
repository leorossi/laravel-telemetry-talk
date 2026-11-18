@extends('layouts.app')

@section('title', 'Logs and traces')

@section('content')
    <h1>Logs and traces</h1>
    <p class="lede">
        OpenTelemetry writes the <code>trace_id</code> into Laravel's log context, so every log line carries the trace
        it belongs to. That is the bridge between two of the three signals: logs tell you <i>that</i> something
        happened, traces tell you <i>where</i>, and the id is what connects the two. Turn the switch off, write a
        line, and watch the id disappear.
    </p>

    <h2>The switch</h2>
    <div class="grid">
        <div class="card">
            <h3>Trace id in log lines</h3>
            <p>
                <span class="pill {{ $injectTraceId ? 'good' : 'warn' }}">{{ $injectTraceId ? 'on' : 'off' }}</span>
                <code>opentelemetry.logs.inject_trace_id</code>
            </p>
            <form method="POST" action="{{ route('demo.logs.toggle') }}">
                @csrf
                <button type="submit" class="btn {{ $injectTraceId ? 'on' : '' }}">
                    Turn it {{ $injectTraceId ? 'off' : 'on' }}
                </button>
            </form>
        </div>

        <div class="card">
            <h3>Write a log line</h3>
            <p>
                Writes one entry right now, from a request that <i>is</i> traced. Two of the three signals are already
                linked — the switch decides whether this is the third.
            </p>
            <form method="POST" action="{{ route('demo.logs.write') }}">
                @csrf
                <button type="submit" class="btn primary">Write a log line</button>
            </form>
        </div>
    </div>

    <h2>Entries written from this page</h2>
    <p style="color: var(--muted); font-size: 0.9rem; margin-top: -0.5rem;">
        Read straight out of <code>{{ $logPath }}</code>, newest first.
    </p>
    <div class="card">
        @forelse ($entries as $entry)
            <div style="padding: 0.7rem 0; {{ $loop->last ? '' : 'border-bottom: 1px solid var(--line);' }}">
                <div class="mono" style="font-size: 0.78rem; word-break: break-all; color: #c9d1e4;">
                    {{ \Illuminate\Support\Str::limit($entry['header'], 240) }}
                </div>

                @if ($entry['extraLines'] > 0)
                    <div style="color: var(--muted); font-size: 0.76rem; margin-top: 0.2rem;">
                        +{{ $entry['extraLines'] }} more line{{ $entry['extraLines'] === 1 ? '' : 's' }} (stack trace)
                    </div>
                @endif

                <div style="margin-top: 0.35rem; font-size: 0.82rem;">
                    @if ($entry['traceId'])
                        <span class="pill good">trace_id</span>
                        <a href="{{ $jaegerUrl }}/trace/{{ $entry['traceId'] }}" target="_blank" rel="noreferrer" class="mono">{{ $entry['traceId'] }}</a>
                        <span style="color: var(--muted);">&rarr; open in Jaeger</span>
                    @else
                        <span class="pill warn">no trace_id</span>
                        <span style="color: var(--muted);">this line is not connected to any trace</span>
                    @endif
                </div>
            </div>
        @empty
            <p>No log entries yet. Write one above.</p>
        @endforelse
    </div>

    <p style="color: var(--muted); font-size: 0.85rem; margin-top: 1.5rem;">
        Jaeger stores spans, not logs. What makes this work is not shipping the log anywhere — it is the id being in
        the line, so a log viewer can link to the trace and a trace can point back at its logs.
    </p>
@endsection
