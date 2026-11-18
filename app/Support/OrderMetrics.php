<?php

namespace App\Support;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

/**
 * The Prometheus metrics for the order demo.
 *
 * Prometheus scrapes /metrics every few seconds. Nothing is pushed.
 */
class OrderMetrics
{
    /**
     * Metric namespace, so the names come out as demo_orders_created_total and
     * friends.
     */
    public const NAMESPACE = 'demo';

    public function __construct(protected MetricsRegistry $metrics) {}

    /**
     * Record one order. Two counters and one histogram:
     *
     *   demo_orders_created_total        counter   how many orders
     *   demo_order_revenue_cents_total   counter   cumulative revenue, in cents
     *   demo_order_value_cents           histogram the distribution of order values
     *
     * "Revenue in the last 10 minutes" is not stored anywhere. It is a query over
     * the counter: increase(demo_order_revenue_cents_total[10m]).
     */
    public function recordOrder(int $totalCents, string $currency = 'EUR'): void
    {
        $registry = $this->registry();

        $registry->getOrRegisterCounter(
            self::NAMESPACE,
            'orders_created_total',
            'Orders created.',
            ['currency'],
        )->inc([$currency]);

        $registry->getOrRegisterCounter(
            self::NAMESPACE,
            'order_revenue_cents_total',
            'Cumulative order revenue in cents.',
            ['currency'],
        )->incBy($totalCents, [$currency]);

        $registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'order_value_cents',
            'Order value in cents.',
            ['currency'],
            [500, 1000, 2500, 5000, 10000, 25000, 50000, 100000],
        )->observe($totalCents, [$currency]);
    }

    /**
     * The Prometheus text exposition format, as scraped from /metrics.
     */
    public function render(): string
    {
        $this->ensureExposed();

        return (new RenderTextFormat)->render($this->registry()->getMetricFamilySamples());
    }

    /**
     * Make sure the counters appear in the exposition even before the first
     * order, by touching them at zero.
     *
     * A counter that has never been incremented has no row in the storage, so it
     * is simply absent from /metrics. Prometheus would then start the series at
     * whatever the first scrape happened to catch — and a series that begins at
     * 5 looks like it never moved, so increase() over it returns 0. Writing a
     * zero first gives Prometheus something to compare against.
     */
    public function ensureExposed(string $currency = 'EUR'): void
    {
        $registry = $this->registry();

        foreach (['orders_created_total', 'order_revenue_cents_total'] as $name) {
            $registry->getOrRegisterCounter(
                self::NAMESPACE,
                $name,
                $name === 'orders_created_total'
                    ? 'Orders created.'
                    : 'Cumulative order revenue in cents.',
                ['currency'],
            )->incBy(0, [$currency]);
        }
    }

    public function registry(): CollectorRegistry
    {
        return $this->metrics->registry();
    }

    /**
     * Forget every counter recorded so far. Used by the reset button on /orders.
     */
    public function wipe(): void
    {
        $this->metrics->wipe();
    }
}
