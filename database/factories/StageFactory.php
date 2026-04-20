<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Lead\Models\Stage;

/**
 * @extends Factory<\Webkul\Lead\Models\Stage>
 */
class StageFactory extends Factory
{
    protected $model = Stage::class;

    public function definition(): array
    {
        return [
            'lead_pipeline_id' => 1,
            'code'             => $this->faker->unique()->slug(1),
            'name'             => $this->faker->words(2, true),
            'probability'      => $this->faker->numberBetween(0, 100),
            'sort_order'       => $this->faker->numberBetween(1, 10),
            'is_won'           => false,
            'is_lost'          => false,
            'is_default'       => false,
        ];
    }

    public function won(): static
    {
        return $this->state(fn () => [
            'is_won'      => true,
            'is_lost'     => false,
            'probability' => 100,
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn () => [
            'is_won'      => false,
            'is_lost'     => true,
            'probability' => 0,
        ]);
    }
}
