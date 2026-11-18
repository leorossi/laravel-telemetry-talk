<?php

namespace Tests\Feature;

use App\Events\OrderPlaced;
use App\Livewire\Pulse\OrdersCard;
use App\Models\Order;
use App\Pulse\Recorders\Orders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pulse\Facades\Pulse;
use Livewire\Livewire;
use Tests\TestCase;

class PulseCardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * phpunit.xml disables Pulse (PULSE_ENABLED=false), so the provider does not
     * subscribe the recorders to Laravel's events. Call the recorder directly:
     * the test covers the recorder, the storage driver and the card, which is
     * what the talk is about, without booting Pulse in every other test.
     */
    public function test_a_domain_event_becomes_a_pulse_entry_and_value(): void
    {
        $order = Order::factory()->create([
            'reference' => 'PULSE-TEST',
            'currency' => 'EUR',
            'total_cents' => 9999,
            'placed_at' => now(),
        ]);

        Pulse::startRecording();

        app(Orders::class)->record(new OrderPlaced($order));
        Pulse::ingest();

        $this->assertDatabaseHas('pulse_entries', [
            'type' => 'order_placed',
            'key' => 'EUR',
            'value' => 9999,
        ]);

        $this->assertDatabaseHas('pulse_aggregates', [
            'type' => 'order_placed',
            'aggregate' => 'sum',
        ]);

        $this->assertDatabaseHas('pulse_values', [
            'type' => 'last_order',
            'key' => 'demo',
        ]);

        $this->assertDatabaseHas('pulse_values', [
            'type' => 'recent_orders',
            'key' => 'demo',
        ]);
    }

    public function test_the_custom_card_renders_the_recorded_totals(): void
    {
        $order = Order::factory()->create([
            'reference' => 'PULSE-TEST',
            'currency' => 'EUR',
            'total_cents' => 9999,
            'placed_at' => now(),
        ]);

        Pulse::startRecording();

        app(Orders::class)->record(new OrderPlaced($order));
        Pulse::ingest();

        Livewire::withoutLazyLoading();

        Livewire::test(OrdersCard::class)
            ->assertSee('Orders')
            ->assertSee('€ 99.99')
            ->assertSee('PULSE-TEST');
    }
}
