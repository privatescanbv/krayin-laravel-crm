<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'title'          => $this->faker->sentence(3),
            'sales_order_id' => $this->faker->unique()->bothify('SO-#####'),
            'total_price'    => $this->faker->randomFloat(2, 10, 5000),
        ];
    }
}

