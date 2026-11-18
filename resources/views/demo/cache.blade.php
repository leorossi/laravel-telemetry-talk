@extends('layouts.app')

@section('title', 'Cache miss then hit')

@section('content')
    <h1>Cache miss &rarr; hit <span class="pill {{ $hit ? 'good' : 'warn' }}">{{ $hit ? 'hit' : 'miss' }}</span></h1>
    <p class="lede">
        The cached payload is sixty day-by-day revenue queries. On a miss you pay for all of them. On a hit you pay
        for one cache read. Key <code>{{ $cacheKey }}</code> on the <code>{{ $cacheStore }}</code> store.
    </p>

    <div class="grid">
        <div class="card">
            <div class="stat">
                @if ($hit)
                    &lt; 1 ms
                    <small>cache read, no recompute</small>
                @else
                    {{ $computeMs }} ms
                    <small>recompute you just paid for</small>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="stat">&euro;{{ number_format(array_sum($series) / 100, 2) }} <small>60 days of revenue in the payload</small></div>
        </div>
        <div class="card">
            <div class="stat">{{ count($series) }} <small>queries the cache is hiding</small></div>
        </div>
    </div>

    <h2>Walk it in two clicks</h2>
    <div class="row" style="gap: 0.5rem;">
        <a class="btn primary" href="{{ route('demo.cache', ['clear' => 1]) }}">1 &mdash; clear and recompute (miss)</a>
        <a class="btn" href="{{ route('demo.cache') }}">2 &mdash; reload (hit)</a>
        <button type="button" class="btn" data-burst-url="{{ route('demo.cache') }}" data-burst-count="10">Hit it 10 times</button>
    </div>

    <h2>Recent days</h2>
    <div class="card">
        @php $peak = max($series) ?: 1; @endphp
        <div class="bars">
            @foreach ($series as $date => $cents)
                <span style="height: {{ max(2, (int) round($cents / $peak * 80)) }}px" title="{{ $date }}"></span>
            @endforeach
        </div>
    </div>
@endsection
