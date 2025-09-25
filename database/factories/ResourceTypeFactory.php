<?php

namespace Database\Factories;

use App\Models\ResourceType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceTypeFactory extends Factory
{
    protected $model = ResourceType::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->word().'-type',
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
