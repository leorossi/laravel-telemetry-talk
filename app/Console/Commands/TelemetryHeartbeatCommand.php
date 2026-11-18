<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

class TelemetryHeartbeatCommand extends Command
{
    protected $signature = 'demo:heartbeat';

    protected $description = 'Roll up the demo dataset so the scheduler has work worth tracing';

    public function handle(): int
    {
        Sleep::for(400)->milliseconds();

        $snapshot = [
            'ran_at' => now()->toIso8601String(),
            'orders' => Order::query()->count(),
            'items' => OrderItem::query()->count(),
            'customers' => User::query()->count(),
            'revenue_today_cents' => (int) Order::query()
                ->whereDate('placed_at', now()->toDateString())
                ->sum('total_cents'),
        ];

        Cache::put('demo:heartbeat', $snapshot, now()->addMinutes(15));

        Log::info('demo:heartbeat completed', $snapshot);

        $this->components->info(sprintf(
            'Heartbeat: %d orders, %d items, %d customers.',
            $snapshot['orders'],
            $snapshot['items'],
            $snapshot['customers'],
        ));

        return self::SUCCESS;
    }
}
