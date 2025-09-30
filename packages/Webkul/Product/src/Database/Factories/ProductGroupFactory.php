<?php

namespace Webkul\Product\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Product\Models\ProductGroup;

class ProductGroupFactory extends Factory
{
    protected $model = ProductGroup::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'parent_id'   => null,
        ];
    }

    /**
     * Indicate that the product group has a parent.
     */
    public function withParent(ProductGroup $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }
}