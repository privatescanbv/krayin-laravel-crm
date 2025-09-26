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
            'partner_name' => $this->faker->unique()->company(),
            'description'  => $this->faker->sentence(8),
        ];
    }
}

