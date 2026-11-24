<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id'    => Order::factory(),
            'product_id'  => Product::factory(),
            'person_id'   => Person::factory(),
            'quantity'    => $this->faker->numberBetween(1, 10),
            'total_price' => $this->faker->randomFloat(2, 5, 1000),
        ];
    }
}
