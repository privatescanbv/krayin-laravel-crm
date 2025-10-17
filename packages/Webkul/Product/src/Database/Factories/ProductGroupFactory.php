<?php

namespace Webkul\Product\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Webkul\Product\Models\ProductGroup;

class ProductGroupFactory extends Factory
{
    protected $model = ProductGroup::class;

    public function definition(): array
    {
        return [
            'name'        => 'Group '.strtoupper(Str::random(6)),
            'description' => 'Description '.strtoupper(Str::random(12)),
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