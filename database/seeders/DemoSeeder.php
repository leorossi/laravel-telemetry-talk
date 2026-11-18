<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::factory(30)->create();

        Order::factory(240)
            ->recycle($customers)
            ->create()
            ->each(function (Order $order): void {
                $items = OrderItem::factory(fake()->numberBetween(3, 6))->create([
                    'order_id' => $order->id,
                ]);

                $order->update([
                    'total_cents' => $items->sum(
                        fn (OrderItem $item): int => $item->quantity * $item->unit_price_cents,
                    ),
                ]);
            });
    }
}
