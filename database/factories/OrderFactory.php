<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reference' => 'ORD-'.Str::upper(Str::random(8)),
            'status' => fake()->randomElement(['paid', 'paid', 'paid', 'shipped', 'refunded']),
            'currency' => 'EUR',
            'total_cents' => 0,
            'placed_at' => fake()->dateTimeBetween('-90 days', 'now'),
        ];
    }
}
