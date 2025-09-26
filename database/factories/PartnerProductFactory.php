<?php

namespace Database\Factories;

use App\Models\PartnerProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerProductFactory extends Factory
{
    protected $model = PartnerProduct::class;

    public function definition(): array
    {
        return [
            'name'               => $this->faker->unique()->words(3, true),
            'currency'           => 'EUR',
            'sales_price'        => $this->faker->randomFloat(2, 10, 2000),
            'active'             => true,
            'description'        => $this->faker->sentence(8),
            'discount_info'      => $this->faker->boolean(30) ? $this->faker->sentence(6) : null,
            'resource_type_id'   => null,
            'partner_name'       => $this->faker->unique()->company(),
            'clinic_description' => $this->faker->boolean(50) ? $this->faker->sentence(10) : null,
            'duration'           => $this->faker->numberBetween(15, 240),
        ];
    }
}
