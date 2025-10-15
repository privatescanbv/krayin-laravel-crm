<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceOrderItemFactory extends Factory
{
    protected $model = ResourceOrderItem::class;

    public function definition(): array
    {
        $from = $this->faker->dateTimeBetween('+1 days', '+2 days');
        $to = (clone $from)->modify('+1 hour');

        return [
            'resource_id'  => Resource::factory(),
            'orderitem_id' => OrderItem::factory(),
            'from'         => $from,
            'to'           => $to,
        ];
    }
}
