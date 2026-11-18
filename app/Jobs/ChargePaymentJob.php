<?php

namespace App\Jobs;

use App\Exceptions\PaymentDeclinedException;
use App\Models\Order;
use App\Support\Chaos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Sleep;

class ChargePaymentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $orderId) {}

    public function backoff(): array
    {
        return [1, 3];
    }

    public function handle(Chaos $chaos): void
    {
        Sleep::for(400)->milliseconds();

        $failureRate = (int) $chaos->get('job_failure_rate', 0);

        if ($failureRate > 0 && random_int(1, 100) <= $failureRate) {
            throw new PaymentDeclinedException(
                "Payment provider declined charge for order {$this->orderId}."
            );
        }

        Order::query()->whereKey($this->orderId)->update(['status' => 'paid']);
    }
}
