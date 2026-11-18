<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelescopeDemoController extends Controller
{
    /**
     * A deliberately small request with one of everything Telescope shows:
     * a few queries, a cache read and a log line. It is the specimen the
     * presenter opens in the Telescope UI during the talk.
     */
    public function __invoke(): View
    {
        $cacheKey = 'demo:telescope:order-count';
        $cacheHit = Cache::has($cacheKey);

        $orderCount = Cache::remember(
            $cacheKey,
            now()->addSeconds(30),
            fn (): int => Order::query()->count(),
        );

        $latestOrders = Order::query()
            ->withCount('items')
            ->latest('placed_at')
            ->limit(5)
            ->get(['id', 'reference', 'status', 'total_cents', 'placed_at']);

        Log::info('Telescope demo request', [
            'source' => 'TelescopeDemoController',
            'cache_hit' => $cacheHit,
            'orders' => $orderCount,
        ]);

        return view('demo.telescope', [
            'cacheKey' => $cacheKey,
            'cacheHit' => $cacheHit,
            'orderCount' => $orderCount,
            'latestOrders' => $latestOrders,
            'telescopeUrl' => url('/telescope'),
        ]);
    }
}
