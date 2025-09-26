<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\ResourceType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        $resourceType = ResourceType::query()->inRandomOrder()->first() ?? ResourceType::factory()->create();

        return [
            'resource_type_id' => $resourceType->id,
            'name'             => $this->faker->unique()->words(2, true),
            'clinic_id'        => null,
        ];
    }
}
