<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderRegel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderRegel>
 */
class OrderRegelFactory extends Factory
{
    protected $model = OrderRegel::class;

    public function definition(): array
    {
        return [
            'order_id'    => Order::factory(),
            'product_id'  => \Webkul\Product\Models\Product::factory(),
            'quantity'    => $this->faker->numberBetween(1, 10),
            'total_price' => $this->faker->randomFloat(2, 5, 1000),
        ];
    }
}

