<?php

namespace Database\Factories;

use App\Enums\PipelineType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Lead\Models\Pipeline;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Webkul\Lead\Models\Pipeline>
 */
class PipelineFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Pipeline::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'        => fake()->word.' Pipeline',
            'rotten_days' => $this->faker->numberBetween(30, 90),
            'is_default'  => 0,
            'type'        => PipelineType::LEAD,
        ];
    }

    /**
     * Indicate that the pipeline is a workflow pipeline.
     */
    public function workflow(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PipelineType::BACKOFFICE,
        ]);
    }

    /**
     * Indicate that the pipeline is a lead pipeline.
     */
    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PipelineType::LEAD,
        ]);
    }

    /**
     * Indicate that the pipeline is the default pipeline.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => 1,
        ]);
    }

    /**
     * Indicate that the pipeline is not the default pipeline.
     */
    public function nonDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => 0,
        ]);
    }
}
