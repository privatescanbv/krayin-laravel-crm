<?php

namespace Database\Factories;

use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductTypeFactory extends Factory
{
    protected $model = ProductType::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->words(2, true),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
