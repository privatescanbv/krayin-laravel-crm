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
            'is_active'                => false,
            'name'                     => $this->faker->unique()->company(),
            'website_url'              => $this->faker->url(),
            'order_confirmation_note'  => $this->faker->optional()->sentence(),
            'emails'                   => [$this->faker->unique()->companyEmail()],
            'phones'                   => [$this->faker->phoneNumber()],
        ];
    }
}
