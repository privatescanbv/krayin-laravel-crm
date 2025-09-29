<?php

namespace Database\Factories;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicFactory extends Factory
{
    protected $model = Clinic::class;

    public function definition(): array
    {
        return [
            'name'   => $this->faker->unique()->company(),
            'soort'  => $this->faker->randomElement(['Algemeen', 'Specialist', 'Tandarts', 'Fysiotherapie']),
            'emails' => [$this->faker->unique()->companyEmail()],
            'phones' => [$this->faker->phoneNumber()],
        ];
    }
}
