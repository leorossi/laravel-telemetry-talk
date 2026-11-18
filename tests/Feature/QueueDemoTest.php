<?php

namespace Tests\Feature;

use App\Jobs\ChargePaymentJob;
use App\Jobs\ReserveStockJob;
use App\Jobs\SendReceiptJob;
use App\Jobs\SyncInventoryJob;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class QueueDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Order::factory(3)
            ->create()
            ->each(fn (Order $order) => OrderItem::factory(4)->create(['order_id' => $order->id]));
    }

    public function test_jobs_page_renders_without_a_batch(): void
    {
        $this->get('/jobs')->assertOk()->assertSee('SyncInventoryJob');
    }

    public function test_dispatch_sends_a_chain_and_a_batch(): void
    {
        Bus::fake();

        $this->post('/jobs/dispatch')->assertRedirect();

        Bus::assertChained([
            ReserveStockJob::class,
            ChargePaymentJob::class,
            SendReceiptJob::class,
        ]);

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 8
            && $batch->jobs->every(fn ($job) => $job instanceof SyncInventoryJob));
    }

    public function test_jobs_page_renders_after_a_batch_was_dispatched(): void
    {
        Bus::fake();

        $this->post('/jobs/dispatch')->assertRedirect();

        $this->get('/jobs')->assertOk();
    }
}
