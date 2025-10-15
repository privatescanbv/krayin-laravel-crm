<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\SalesLead;
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
            'total_price'    => $this->faker->randomFloat(2, 10, 5000),
            'sales_lead_id'  => SalesLead::factory(),
            'combine_order'  => true,
        ];
    }
}
