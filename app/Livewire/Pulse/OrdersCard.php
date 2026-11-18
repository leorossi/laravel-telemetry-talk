<?php

namespace App\Livewire\Pulse;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * A custom Pulse card.
 *
 * Extending Laravel\Pulse\Livewire\Card gives us the period selector, the
 * query cache (`remember`) and the aggregate helpers. The card is a normal
 * Livewire component: the markup polls itself every five seconds.
 */
#[Lazy]
class OrdersCard extends Card
{
    public function render(): View
    {
        [$data, $time, $runAt] = $this->remember(function (): array {
            $last = $this->values('last_order')->first();
            $recent = $this->values('recent_orders')->first();

            return [
                'orders' => (int) $this->aggregateTotal('order_placed', 'count'),
                'revenue_cents' => (int) round($this->aggregateTotal('order_placed', 'sum')),
                'recent' => $recent === null
                    ? []
                    : json_decode($recent->value, true, flags: JSON_THROW_ON_ERROR),
                'last_at' => $last?->timestamp,
            ];
        });

        return view('pulse.orders-card', [
            'time' => $time,
            'runAt' => $runAt,
            'orders' => $data['orders'],
            'revenueCents' => $data['revenue_cents'],
            'averageCents' => $data['orders'] > 0
                ? $data['revenue_cents'] / $data['orders']
                : 0.0,
            'recent' => $data['recent'],
            'lastAt' => $data['last_at'] === null
                ? null
                : CarbonImmutable::createFromTimestamp($data['last_at'])->diffForHumans(),
        ]);
    }
}
