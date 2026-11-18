<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\ExternalInventoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Sleep;

class ReserveStockJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId) {}

    public function handle(ExternalInventoryService $inventory): void
    {
        $order = Order::query()->with('items')->findOrFail($this->orderId);

        foreach ($order->items as $item) {
            $inventory->stockFor($item->sku);
        }

        Sleep::for(250)->milliseconds();

        $order->update(['status' => 'stock_reserved']);
    }
}
