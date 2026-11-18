@extends('layouts.app')

@section('title', 'Telescope — one request, under the lens')

@section('content')
    <h1>Telescope <span class="pill">{{ $cacheHit ? 'cache hit' : 'cache miss' }}</span></h1>
    <p class="lede">
        This request is the specimen: {{ $orderCount }} orders counted, a cache {{ $cacheHit ? 'hit' : 'miss' }}, and a
        log line. Telescope records all of it without a collector and without leaving your machine.
    </p>

    <div class="grid">
        <div class="card">
            <div class="stat">{{ $orderCount }} <small>orders, read through the cache</small></div>
        </div>
        <div class="card">
            <div class="stat">{{ $cacheHit ? 'hit' : 'miss' }} <small>key <code>{{ $cacheKey }}</code></small></div>
        </div>
        <div class="card">
            <div class="stat">{{ $latestOrders->count() }} <small>latest orders, with their item counts</small></div>
        </div>
    </div>

    <h2>Open it</h2>
    <div class="row" style="gap: 0.5rem;">
        <a class="btn primary" href="{{ $telescopeUrl }}" target="_blank" rel="noreferrer">Open Telescope</a>
        <a class="btn" href="{{ route('demo.telescope') }}">Run this request again</a>
    </div>

    <h2>What to click</h2>
    <div class="card">
        <p>
            In Telescope, open <b>Requests</b>, then the latest <code>GET /demo/telescope</code>. The tabs hold the
            same request from different angles: <b>Queries</b> (the reads above), <b>Cache</b> (the miss or the hit),
            <b>Logs</b> (the line this page just wrote) and <b>Events</b>.
        </p>
        <p>
            This is the cheapest rung: no agent, no collector, no external service. Entries live in the local
            <code>telescope_entries</code> table, which is exactly why it is a development tool and not production
            monitoring.
        </p>
    </div>

    <h2>Latest orders</h2>
    <div class="card">
        @forelse ($latestOrders as $order)
            <div class="row" style="justify-content: space-between; padding: 0.3rem 0;">
                <code>{{ $order->reference }}</code>
                <span>&euro;{{ number_format($order->total_cents / 100, 2) }}</span>
                <span class="pill">{{ $order->items_count }} item{{ $order->items_count === 1 ? '' : 's' }}</span>
                <span class="pill {{ $order->status === 'paid' ? 'good' : 'warn' }}">{{ $order->status }}</span>
            </div>
        @empty
            <p>No orders yet. Telescope still shows the queries and the log line.</p>
        @endforelse
    </div>
@endsection
