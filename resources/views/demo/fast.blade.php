@extends('layouts.app')

@section('title', 'Fast endpoint — cache hit')

@section('content')
    <h1>Fast endpoint <span class="pill {{ $hit ? 'good' : 'warn' }}">{{ $hit ? 'cache hit' : 'cache miss' }}</span></h1>
    <p class="lede">
        This page is the baseline. It reads a cached rollup of the whole order history, and it never touches the
        ninety-day revenue series unless the cache is cold. Key <code>{{ $cacheKey }}</code> on the
        <code>{{ $cacheStore }}</code> store.
    </p>

    <div class="grid">
        <div class="card">
            <div class="stat">
                @if ($hit)
                    from cache
                    <small>the rollup itself costs {{ $computeMs === null ? 'about 90' : $computeMs }} ms to rebuild</small>
                @else
                    {{ $computeMs }} ms
                    <small>this request paid for the rebuild</small>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="stat">{{ number_format($report['orders']) }} <small>orders seeded</small></div>
        </div>
        <div class="card">
            <div class="stat">{{ number_format($report['items']) }} <small>order items</small></div>
        </div>
        <div class="card">
            <div class="stat">&euro;{{ number_format($report['revenue_cents'] / 100, 2) }} <small>lifetime revenue</small></div>
        </div>
    </div>

    <h2>Ninety days of revenue</h2>
    <div class="card">
        @php $peak = max($report['series']) ?: 1; @endphp
        <div class="bars">
            @foreach ($report['series'] as $date => $cents)
                <span style="height: {{ max(2, (int) round($cents / $peak * 80)) }}px" title="{{ $date }}"></span>
            @endforeach
        </div>
        <p style="margin-top: 0.75rem;">
            {{ count($report['series']) }} days. Rebuilding this series is one query per day — that is the work the
            cache is hiding.
        </p>
    </div>

    <h2>Drive it</h2>
    <div class="row" style="gap: 0.5rem;">
        <a class="btn primary" href="{{ route('demo.fast') }}">Reload (expect a hit)</a>
        <a class="btn" href="{{ route('demo.fast', ['refresh' => 1]) }}">Force a miss</a>
        <button type="button" class="btn" data-burst-url="{{ route('demo.fast') }}" data-burst-count="10">Hit it 10 times</button>
    </div>
@endsection
