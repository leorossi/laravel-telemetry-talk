<?php

namespace App\Pulse\Recorders;

use App\Events\OrderPlaced;
use Laravel\Pulse\Pulse;

/**
 * A custom Pulse recorder: one event in, one entry out.
 *
 * The `$listen` property is the contract. Pulse registers this class on
 * Laravel's event dispatcher, so `record()` runs for every OrderPlaced event.
 * The entry is buffered in memory; Pulse flushes the buffer to the ingest
 * driver at the end of the request (or on the next pulse:check beat).
 *
 * `count()` and `sum()` are not queries. They tell the storage driver which
 * aggregates to maintain in pulse_aggregates, so the card can stay fast even
 * when pulse_entries has been trimmed.
 */
class Orders
{
    /**
     * The event this recorder listens for.
     *
     * @var class-string
     */
    public string $listen = OrderPlaced::class;

    /**
     * The last few orders, hydrated from pulse_values on first use so that a
     * burst of twenty orders still produces one useful list.
     *
     * @var null|list<array{reference: string, total_cents: int, currency: string}>
     */
    protected ?array $recent = null;

    public function __construct(protected Pulse $pulse) {}

        public function record(OrderPlaced $event): void
    {
        $order = $event->order;

        $this->pulse->record(
            type: 'order_placed',
            key: $order->currency,
            value: $order->total_cents,
            timestamp: $order->placed_at,
        )->count()->sum();

        // Values are different from entries: Pulse keeps only the latest one
        // per key. Perfect for "what just happened", no aggregation needed.
        $this->pulse->set('last_order', 'demo', json_encode([
            'reference' => $order->reference,
            'total_cents' => $order->total_cents,
            'currency' => $order->currency,
        ], JSON_THROW_ON_ERROR));

        // One value holding the last five orders. The card renders this as a
        // tiny list; it is still a single row in pulse_values.
        $recent = [...$this->recent(), [
            'reference' => $order->reference,
            'total_cents' => $order->total_cents,
            'currency' => $order->currency,
        ]];

        $this->recent = array_slice($recent, -5);

        $this->pulse->set('recent_orders', 'demo', json_encode($this->recent, JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<array{reference: string, total_cents: int, currency: string}>
     */
    protected function recent(): array
    {
        if ($this->recent !== null) {
            return $this->recent;
        }

        $value = $this->pulse->values('recent_orders')->first();

        return $this->recent = $value === null
            ? []
            : json_decode($value->value, true, flags: JSON_THROW_ON_ERROR);
    }
}
