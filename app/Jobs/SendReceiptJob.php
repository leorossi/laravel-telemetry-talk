<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Sleep;

class SendReceiptJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        Sleep::for(150)->milliseconds();

        Order::query()->whereKey($this->orderId)->update(['status' => 'completed']);
    }
}
