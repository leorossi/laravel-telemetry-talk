@extends('layouts.app')

@section('title', 'Queued jobs — chain and batch')

@section('content')
    <h1>Queued jobs <span class="pill">{{ $connection }} queue driver</span></h1>
    <p class="lede">
        One <b>chain</b> of three jobs that must run in order, and one <b>batch</b> of eight jobs that may run in
        parallel. Both write real statuses back to the orders table, and both are slow enough to show up in Pulse,
        in Jaeger and in Nightwatch. Nothing here is worth tracing if the worker is not running:
        <code>php artisan queue:work</code>.
    </p>

    <div class="grid">
        <div class="card">
            <div class="stat">{{ number_format($pending) }} <small>jobs waiting in the queue table</small></div>
        </div>
        <div class="card">
            <div class="stat">{{ number_format($failed) }} <small>failed jobs</small></div>
        </div>
        <div class="card">
            @if ($batch)
                <div class="stat">{{ $batch->processedJobs() }} / {{ $batch->totalJobs }}
                    <small>batch <code>{{ $batch->name }}</code> &mdash; {{ $batch->finished() ? 'finished' : 'still running' }}</small>
                </div>
                @if ($batch->failedJobs > 0)
                    <div class="banner">{{ $batch->failedJobs }} of these failed. Check the chaos panel.</div>
                @endif
            @else
                <div class="stat">&mdash; <small>no batch dispatched yet</small></div>
            @endif
        </div>
    </div>

    <h2>Dispatch</h2>
    <div class="card">
        <form method="POST" action="{{ route('demo.jobs.dispatch') }}">
            @csrf
            <button type="submit" class="btn primary">Dispatch the chain and the batch</button>
        </form>
        <p style="margin-top: 0.9rem; color: var(--muted); font-size: 0.9rem;">
            Chain: <code>ReserveStockJob</code> &rarr; <code>ChargePaymentJob</code> (3 tries, backoff 1s/3s) &rarr;
            <code>SendReceiptJob</code>. Batch: 8 &times; <code>SyncInventoryJob</code>, one per random SKU.
            Reload this page to watch the batch progress.
        </p>
    </div>

    <h2>Make them interesting</h2>
    <div class="row" style="gap: 0.5rem;">
        <a class="btn" href="{{ route('demo.chaos', ['focus' => 'jobs']) }}">Raise the job failure rate</a>
        <a class="btn" href="{{ route('demo.jobs') }}">Reload batch progress</a>
        <button type="button" class="btn" data-burst-url="{{ route('demo.jobs') }}" data-burst-count="5">Poll 5 times</button>
    </div>
@endsection
