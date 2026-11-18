@extends('layouts.app')

@section('title', 'Chaos panel')

@php
    $booleans = [
        'n_plus_one' => [
            'on' => ['1', 'N+1 queries'],
            'off' => ['0', 'eager load the fix'],
        ],
        'external_failure' => [
            'on' => ['1', 'return 503'],
            'off' => ['0', 'return 200'],
        ],
    ];
@endphp

@section('content')
    <h1>Chaos panel</h1>
    <p class="lede">
        The presenter's remote control. Every change is stored in the cache, so it takes effect on the very next
        request — from any tab, any browser, no deploy. If anything on stage goes sideways, the reset button at the
        bottom puts the whole app back.
    </p>

    <h2>Switches</h2>
    <div class="grid">
        @foreach ($booleans as $key => $modes)
            <div class="card">
                <h3>{{ config("telemetry-demo.chaos_labels.{$key}") }}</h3>
                <p><code>{{ $key }}</code></p>
                <form method="POST" action="{{ route('demo.chaos.apply') }}" class="row" style="gap: 0.4rem;">
                    @csrf
                    <input type="hidden" name="key" value="{{ $key }}">
                    @foreach ($modes as $mode => $button)
                        <button
                            type="submit"
                            name="value"
                            value="{{ $button[0] }}"
                            class="btn {{ (bool) $values[$key] === ($mode === 'on') ? 'on' : '' }}"
                        >{{ $button[1] }}</button>
                    @endforeach
                </form>
            </div>
        @endforeach

        @foreach ($presets as $key => $options)
            <div class="card">
                <h3>{{ config("telemetry-demo.chaos_labels.{$key}") }}</h3>
                <p><code>{{ $key }}</code></p>
                <form method="POST" action="{{ route('demo.chaos.apply') }}" class="row" style="gap: 0.4rem;">
                    @csrf
                    <input type="hidden" name="key" value="{{ $key }}">
                    @foreach ($options as $option)
                        <button
                            type="submit"
                            name="value"
                            value="{{ $option }}"
                            class="btn {{ (int) $values[$key] === $option ? 'on' : '' }}"
                        >{{ $option === 0 ? 'off' : $option.($key === 'job_failure_rate' ? '%' : ' ms') }}</button>
                    @endforeach
                </form>
            </div>
        @endforeach
    </div>

    <h2>Current state</h2>
    <div class="card">
        <table>
            <thead>
            <tr>
                <th>Switch</th>
                <th>Value</th>
                <th>Default</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($values as $key => $value)
                <tr>
                    <td><code>{{ $key }}</code></td>
                    <td>
                        @if (is_bool($value))
                            <span class="pill {{ $value === ($defaults[$key] ?? null) ? '' : 'warn' }}">{{ $value ? 'on' : 'off' }}</span>
                        @else
                            <span class="pill {{ $value === ($defaults[$key] ?? null) ? '' : 'warn' }}">{{ $value }}</span>
                        @endif
                    </td>
                    <td>{{ is_bool($defaults[$key]) ? ($defaults[$key] ? 'on' : 'off') : $defaults[$key] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <h2>One-off overrides, no state</h2>
    <div class="card">
        <p>
            Any switch can also be passed as a query parameter on any demo URL. It applies to that single request and
            is never written to the cache:
        </p>
        <pre class="mono"><code>{{ url('/slow') }}?n_plus_one=0&amp;db_sleep_ms=250</code></pre>
        <p style="color: var(--muted); font-size: 0.9rem;">
            Useful when you want a clean "before" and "after" waterfall without touching the switches mid-sentence.
        </p>
    </div>

    <h2>Reset</h2>
    <div class="card">
        <form method="POST" action="{{ route('demo.chaos.reset') }}">
            @csrf
            <button type="submit" class="btn primary">Reset every switch to its default</button>
        </form>
        <p style="margin-top: 0.75rem; color: var(--muted); font-size: 0.9rem;">
            There are currently <b>{{ count($changed) }}</b> switched-away-from-default values.
        </p>
    </div>
@endsection
