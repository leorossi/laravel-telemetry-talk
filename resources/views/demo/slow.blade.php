@extends('layouts.app')

@section('title', 'Slow endpoint — N+1 queries')

@section('content')
    <h1>
        Slow endpoint
        <span class="pill {{ $eagerLoad ? 'good' : 'bad' }}">{{ $eagerLoad ? 'relations eager loaded' : 'N+1 enabled' }}</span>
    </h1>
    <p class="lede">
        {{ $perPage }} orders, each one touching its customer and its items. With the N+1 on, this page is one query
        for the page, one for the count, and two more per row. Check the query counter in the footer.
    </p>

    <div class="row" style="gap: 0.5rem;">
        <a class="btn {{ $eagerLoad ? '' : 'on' }}" href="{{ route('demo.slow', ['n_plus_one' => 1]) }}">N+1 on</a>
        <a class="btn {{ $eagerLoad ? 'on' : '' }}" href="{{ route('demo.slow', ['n_plus_one' => 0]) }}">Eager loaded (the fix)</a>
        <a class="btn" href="{{ route('demo.chaos') }}">Make the external API slow</a>
    </div>

    <h2>External inventory API</h2>
    <div class="card">
        @if ($stockError)
            <div class="banner">External call failed after retries: {{ $stockError }}</div>
        @else
            <p>
                <code>GET {{ config('telemetry-demo.external.base_url') }}/api/stock/{{ $sku }}</code>
                &rarr; on hand <b>{{ $stock['on_hand'] }}</b>, warehouse <b>{{ $stock['warehouse'] }}</b>,
                checked <code>{{ $stock['checked_at'] }}</code>.
            </p>
        @endif
        <p style="color: var(--muted); font-size: 0.9rem; margin: 0;">
            This is the one span that is not our code. Raise its latency on the chaos panel and watch the waterfall.
        </p>
    </div>

    <h2>Orders</h2>
    <div class="card">
        <table>
            <thead>
            <tr>
                <th>Reference</th>
                <th>Placed</th>
                <th>Customer</th>
                <th>Items</th>
                <th class="num">Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($orders as $order)
                <tr>
                    <td class="mono">{{ $order->reference }}</td>
                    <td>{{ $order->placed_at->diffForHumans() }}</td>
                    <td>{{ $order->customer->name }}</td>
                    <td>{{ $order->items->count() }}</td>
                    <td class="num">&euro;{{ number_format($order->total_cents / 100, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="row" style="gap: 0.5rem; margin-top: 1rem; align-items: center;">
            @if ($orders->onFirstPage())
                <span class="pill">start</span>
            @else
                <a class="btn tiny" href="{{ $orders->previousPageUrl() }}">Previous</a>
            @endif
            <span class="pill">page {{ $orders->currentPage() }} of {{ $orders->lastPage() }}</span>
            @if ($orders->hasMorePages())
                <a class="btn tiny" href="{{ $orders->nextPageUrl() }}">Next</a>
            @endif
        </div>
    </div>
@endsection
