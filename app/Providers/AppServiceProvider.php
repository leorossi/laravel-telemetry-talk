<?php

namespace App\Providers;

use App\Livewire\Pulse\OrdersCard;
use App\Support\Chaos;
use App\Support\HttpMetrics;
use App\Support\MetricsRegistry;
use App\Support\OrderMetrics;
use App\Support\PrometheusQuery;
use App\Support\QueueMetrics;
use App\Support\RequestMetrics;
use App\Support\SimulatedExternalService;
use App\Support\TraceIdInLogs;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\Query;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Chaos::class);
        $this->app->singleton(RequestMetrics::class);

        // One Prometheus registry per process: the client keeps its counters in
        // Postgres, but the registry object itself is per-process. Every metric
        // family shares it, so /metrics is one snapshot and reset wipes it once.
        $this->app->singleton(MetricsRegistry::class);
        $this->app->singleton(OrderMetrics::class);
        $this->app->singleton(HttpMetrics::class);
        $this->app->singleton(QueueMetrics::class);

        $this->app->singleton(PrometheusQuery::class, fn (): PrometheusQuery => new PrometheusQuery(
            (string) config('telemetry-demo.prometheus.internal_url'),
        ));

        // Telescope, Pulse and the queue/cache tables write rows on every page
        // view and every poll. That is infrastructure talking, not application
        // behaviour: keep it out of the Nightwatch budget. Registered here, not
        // in boot(), because Telescope reads the cache while its provider boots
        // and those queries would slip past a filter registered later.
        Nightwatch::rejectQueries(fn (Query $query): bool => str_contains($query->sql, 'telescope_entries')
            || str_contains($query->sql, 'telescope_monitoring')
            || str_contains($query->sql, 'pulse_')
            || str_contains($query->sql, 'into "jobs"')
            || str_contains($query->sql, 'from "cache"')
            || str_contains($query->sql, 'into "cache"'));
    }

    public function boot(): void
    {
        Gate::define('viewPulse', fn ($user = null): bool => true);

        // The custom card is a normal Livewire component. Pulse registers its
        // own cards this way too; ours lives in app/Livewire/Pulse and reads
        // the entries written by App\Pulse\Recorders\Orders.
        Livewire::component('pulse.orders', OrdersCard::class);

        // The /logs page can switch the trace id in log lines on and off. The
        // OpenTelemetry package reads this config while the request is being
        // handled, so setting it here — before any middleware runs — flips the
        // real behaviour instead of re-implementing it. On by default, like the
        // package itself.
        config([
            'opentelemetry.logs.inject_trace_id' => $this->app->make(TraceIdInLogs::class)->enabled(),
        ]);

        $this->app->make(SimulatedExternalService::class)->register();
    }
}
