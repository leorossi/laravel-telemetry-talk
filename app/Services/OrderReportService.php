<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderReportService
{
    public function dashboard(): array
    {
        return [
            'orders' => Order::query()->count(),
            'items' => OrderItem::query()->count(),
            'customers' => User::query()->count(),
            'revenue_cents' => (int) Order::query()->sum('total_cents'),
            'series' => $this->revenueSeries(),
        ];
    }

    public function revenueSeries(int $days = 90): array
    {
        $series = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = now()->subDays($offset)->toDateString();

            $series[$date] = (int) Order::query()
                ->whereDate('placed_at', $date)
                ->sum('total_cents');
        }

        return $series;
    }

    public function recentOrders(int $perPage, bool $eagerLoad): LengthAwarePaginator
    {
        $query = Order::query()->latest('placed_at');

        if ($eagerLoad) {
            $query->with(['customer', 'items']);
        }

        return $query->paginate($perPage);
    }
}
