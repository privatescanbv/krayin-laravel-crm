<?php

namespace Database\Factories;

use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        return [
            'type'      => $this->faker->randomElement(['staff', 'machine', 'room']),
            'name'      => $this->faker->unique()->words(2, true),
            'clinic_id' => null,
        ];
    }
}

