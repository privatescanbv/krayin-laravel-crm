<?php

namespace Webkul\Product\Database\Factories;

use App\Models\ProductType;
use App\Models\ResourceType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductGroup;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name'              => 'Product '.strtoupper(Str::random(6)),
            'currency'          => 'EUR',
            'description'       => 'Description '.strtoupper(Str::random(16)),
            'price'             => $this->faker->randomFloat(2, 10, 2000),
            'product_group_id'  => null,
            'resource_type_id'  => null,
            'product_type_id'   => null,
        ];
    }
}
