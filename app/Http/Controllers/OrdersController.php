<?php

namespace App\Http\Controllers;

use App\Events\OrderPlaced;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\OrderMetrics;
use App\Support\PrometheusQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;

class OrdersController extends Controller
{
    /**
     * The windows the page reports on, in minutes.
     */
    public const WINDOWS = [1, 2, 10];

    /**
     * Orders created from this page carry this reference prefix, so the reset
     * button can delete exactly those and leave the seeded data alone.
     */
    public const DEMO_REFERENCE = 'DEMO-';

    public function __construct(
        protected OrderMetrics $metrics,
        protected PrometheusQuery $prometheus,
    ) {}

    public function index(): View
    {
        return view('orders', [
            'windows' => $this->windowReport(),
            'totalOrders' => Order::query()->count(),
            'totalRevenueCents' => (int) Order::query()->sum('total_cents'),
            'prometheusUrl' => config('telemetry-demo.prometheus.public_url'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $count = max(1, min(20, (int) $request->input('burst', 1)));

        $revenueCents = 0;

        for ($i = 0; $i < $count; $i++) {
            $order = $this->createRandomOrder();

            // The domain event feeds the custom Pulse recorder. Prometheus is
            // updated separately: same order, two different lenses.
            OrderPlaced::dispatch($order);

            $this->metrics->recordOrder($order->total_cents, $order->currency);

            $revenueCents += $order->total_cents;
        }

        return back()->with('status', $count === 1
            ? sprintf('Created 1 order for €%s and recorded it as a metric.', number_format($revenueCents / 100, 2))
            : sprintf('Created %d orders for €%s and recorded them as metrics.', $count, number_format($revenueCents / 100, 2)));
    }

    public static function revenueQuery(int $minutes): string
    {
        return sprintf('sum(increase(demo_order_revenue_cents_total[%dm])) / 100', $minutes);
    }

    public static function ordersQuery(int $minutes): string
    {
        return sprintf('sum(increase(demo_orders_created_total[%dm]))', $minutes);
    }

    /**
     * Put the demo back to a clean slate: remove the orders this page created,
     * wipe the counters, and tell Prometheus to forget the series.
     *
     * The last step matters. To Prometheus a counter that falls back to zero
     * looks like a process restart, and increase() would invent a spike from it.
     */
    public function reset(): RedirectResponse
    {
        $deleted = Order::query()
            ->where('reference', 'like', self::DEMO_REFERENCE.'%')
            ->delete();

        // The custom card reads its own Pulse types, so wipe those too:
        // otherwise the orders would be gone from Postgres and Prometheus but
        // still counted on the Pulse dashboard.
        Pulse::purge(['order_placed', 'last_order', 'recent_orders']);

        // Delete the series first, then zero the counters. The other order lets a
        // scrape happen in between, and Prometheus would see the counter drop and
        // treat it as a process restart.
        $cleared = $this->prometheus->deleteSeries('{__name__=~"demo_.*"}');

        $this->metrics->wipe();

        return back()->with('status', sprintf(
            'Deleted %d order%s created here and reset the counters%s.',
            $deleted,
            $deleted === 1 ? '' : 's',
            $cleared ? ', clearing their history in Prometheus too' : ' (Prometheus history was not cleared)',
        ));
    }

    /**
     * Ask Prometheus what happened in each window.
     *
     * The page does not compute these from the database: it reads the numbers
     * back out of Prometheus, so what you see is what Prometheus knows.
     *
     * @return array<int, array{minutes: int, revenue: ?float, orders: ?float, url: string}>
     */
    protected function windowReport(): array
    {
        $report = [];

        foreach (self::WINDOWS as $minutes) {
            $revenueQuery = self::revenueQuery($minutes);

            $report[] = [
                'minutes' => $minutes,
                'revenue' => $this->prometheus->scalar($revenueQuery),
                'orders' => $this->prometheus->scalar(self::ordersQuery($minutes)),
                'url' => $this->prometheusUrl($revenueQuery),
            ];
        }

        return $report;
    }

    protected function prometheusUrl(string $query): string
    {
        return sprintf(
            '%s/graph?g0.expr=%s&g0.tab=0',
            rtrim((string) config('telemetry-demo.prometheus.public_url'), '/'),
            rawurlencode($query),
        );
    }

    /**
     * A random order, built with the same factories the seeder uses, so it looks
     * like every other order in the database. The total is the sum of its items.
     */
    protected function createRandomOrder(): Order
    {
        return DB::transaction(function (): Order {
            $order = Order::factory()->create([
                'user_id' => User::query()->inRandomOrder()->firstOrFail()->id,
                'reference' => self::DEMO_REFERENCE.Str::upper(Str::random(8)),
                'placed_at' => now(),
            ]);

            $items = OrderItem::factory()
                ->count(random_int(1, 4))
                ->for($order)
                ->create();

            $order->update([
                'total_cents' => $items->sum(
                    fn (OrderItem $item): int => $item->quantity * $item->unit_price_cents
                ),
            ]);

            return $order->refresh();
        });
    }
}
