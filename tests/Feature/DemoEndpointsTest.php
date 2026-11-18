<?php

namespace Tests\Feature;

use App\Exceptions\WarehouseSyncFailedException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Chaos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Order::factory(20)
            ->create()
            ->each(fn (Order $order) => OrderItem::factory(3)->create(['order_id' => $order->id]));
    }

    public function test_home_page_links_to_every_demo_endpoint(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('/slow')
            ->assertSee('/chaos')
            ->assertSee('/jobs');
    }

    public function test_fast_endpoint_misses_then_hits(): void
    {
        $miss = $this->get('/fast')->assertOk();
        $this->assertFalse($miss->viewData('hit'));
        $this->assertIsFloat($miss->viewData('computeMs'));

        $hit = $this->get('/fast')->assertOk();
        $this->assertTrue($hit->viewData('hit'));
        $this->assertNull($hit->viewData('computeMs'));
    }

    public function test_cache_demo_clears_then_serves_from_cache(): void
    {
        $miss = $this->get('/cache?clear=1')->assertOk();
        $this->assertFalse($miss->viewData('hit'));
        $this->assertCount(60, $miss->viewData('series'));

        $hit = $this->get('/cache')->assertOk();
        $this->assertTrue($hit->viewData('hit'));
        $this->assertNull($hit->viewData('computeMs'));
    }

    public function test_slow_endpoint_loads_relations_lazily_by_default(): void
    {
        $naive = $this->countQueries(fn () => $this->get('/slow')->assertOk());
        $eager = $this->countQueries(fn () => $this->get('/slow?n_plus_one=0')->assertOk());

        $this->assertGreaterThan($eager * 2, $naive);
    }

    public function test_slow_endpoint_reports_the_external_inventory_lookup(): void
    {
        $response = $this->get('/slow')->assertOk();

        $this->assertNull($response->viewData('stockError'));
        $this->assertSame('ams-1', $response->viewData('stock')['warehouse']);
    }

    public function test_external_failure_chaos_is_handled_without_a_500(): void
    {
        app(Chaos::class)->set('external_failure', true);

        $response = $this->get('/slow')->assertOk();

        $this->assertNotNull($response->viewData('stockError'));
    }

    public function test_failing_endpoint_throws(): void
    {
        $this->withoutExceptionHandling();

        $this->expectException(WarehouseSyncFailedException::class);

        $this->get('/fail');
    }

    public function test_telescope_demo_endpoint_renders_and_links_to_telescope(): void
    {
        $response = $this->get('/demo/telescope')
            ->assertOk()
            ->assertSee('Open Telescope')
            ->assertSee(url('/telescope'), false);

        $this->assertSame(20, $response->viewData('orderCount'));
        $this->assertCount(5, $response->viewData('latestOrders'));
    }

    protected function countQueries(callable $callback): int
    {
        $queries = 0;

        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $callback();

        return $queries;
    }
}
