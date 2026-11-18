<?php

namespace App\Http\Controllers;

use App\Services\ExternalInventoryService;
use App\Services\OrderReportService;
use App\Support\Chaos;
use Illuminate\Contracts\View\View;
use Throwable;

class SlowOrdersController extends Controller
{
    public function __construct(
        protected OrderReportService $reports,
        protected ExternalInventoryService $inventory,
        protected Chaos $chaos,
    ) {}

    public function __invoke(): View
    {
        $eagerLoad = ! $this->chaos->get('n_plus_one', true);
        $perPage = (int) config('telemetry-demo.slow_page_size');

        $orders = $this->reports->recentOrders($perPage, $eagerLoad);

        if (! $eagerLoad) {
            foreach ($orders as $order) {
                $order->load(['customer', 'items']);
            }
        }

        $sku = $orders->first()?->items->first()?->sku ?? 'SKU-UNKNOWN';

        try {
            $stock = $this->inventory->stockFor($sku);
            $stockError = null;
        } catch (Throwable $exception) {
            $stock = null;
            $stockError = $exception->getMessage();
        }

        return view('demo.slow', [
            'orders' => $orders,
            'eagerLoad' => $eagerLoad,
            'perPage' => $perPage,
            'sku' => $sku,
            'stock' => $stock,
            'stockError' => $stockError,
        ]);
    }
}
