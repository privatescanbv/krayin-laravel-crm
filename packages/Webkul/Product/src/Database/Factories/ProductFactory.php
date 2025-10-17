<?php

namespace Webkul\Product\Database\Factories;

use App\Models\ProductType;
use App\Models\ResourceType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductGroup;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name'              => $this->faker->unique()->sentence(3),
            'currency'          => 'EUR',
            'description'       => $this->faker->sentence(8),
            'price'             => $this->faker->randomFloat(2, 10, 2000),
            'costs'             => $this->faker->randomFloat(2, 5, 1000),
            'product_group_id'  => function () {
                $existingId = ProductGroup::query()->value('id');

                return $existingId ?? ProductGroup::factory()->create()->id;
            },
            'resource_type_id'  => function () {
                $existingId = ResourceType::query()->value('id');

                return $existingId ?? ResourceType::factory()->create()->id;
            },
            'product_type_id'   => function () {
                $existingId = ProductType::query()->value('id');

                return $existingId;
            },
        ];
    }
}
