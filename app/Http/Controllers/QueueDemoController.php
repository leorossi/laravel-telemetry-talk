<?php

namespace App\Http\Controllers;

use App\Jobs\ChargePaymentJob;
use App\Jobs\ReserveStockJob;
use App\Jobs\SendReceiptJob;
use App\Jobs\SyncInventoryJob;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueDemoController extends Controller
{
    public const BATCH_CACHE_KEY = 'demo:last-batch';

    public function index(): View
    {
        $batchId = Cache::get(self::BATCH_CACHE_KEY);

        return view('demo.jobs', [
            'pending' => DB::table('jobs')->count(),
            'failed' => DB::table('failed_jobs')->count(),
            'batch' => $batchId ? Bus::findBatch($batchId) : null,
            'lastBatchId' => $batchId,
            'connection' => config('queue.default'),
        ]);
    }

    public function dispatch(): RedirectResponse
    {
        $order = Order::query()->latest('placed_at')->firstOrFail();

        Bus::chain([
            new ReserveStockJob($order->id),
            new ChargePaymentJob($order->id),
            new SendReceiptJob($order->id),
        ])->catch(function (Throwable $exception) use ($order): void {
            Log::warning('Order chain failed', [
                'order' => $order->reference,
                'message' => $exception->getMessage(),
            ]);
        })->dispatch();

        $batch = Bus::batch(
            OrderItem::query()
                ->inRandomOrder()
                ->limit(8)
                ->get()
                ->map(fn (OrderItem $item): SyncInventoryJob => new SyncInventoryJob($item->sku))
                ->all()
        )
            ->name('inventory-sync')
            ->then(fn () => Log::info('inventory-sync batch finished'))
            ->catch(fn (Throwable $exception) => Log::error('inventory-sync batch failed', [
                'message' => $exception->getMessage(),
            ]))
            ->dispatch();

        Cache::put(self::BATCH_CACHE_KEY, $batch->id, now()->addMinutes(30));

        return back()->with('status', sprintf(
            'Dispatched a chain of 3 jobs for %s and a batch of 8 inventory syncs (%s).',
            $order->reference,
            $batch->id,
        ));
    }
}
