<?php

namespace Database\Factories;

use App\Models\AiSummary;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Models\Lead;

/**
 * @extends Factory<AiSummary>
 */
class AiSummaryFactory extends Factory
{
    protected $model = AiSummary::class;

    public function definition(): array
    {
        return [
            'subject_type'       => (new Lead)->getMorphClass(),
            'subject_id'         => Lead::factory(),
            'summary'            => $this->faker->text(300),
            'next_action_title'  => $this->faker->sentence(5),
            'next_action_reason' => $this->faker->sentence(12),
            'priority'           => $this->faker->randomElement(['low', 'medium', 'high']),
            'highlights'         => [],
            'attention_points'   => [],
            'generated_at'       => now(),
            'model'              => 'test-model',
            'prompt_version'     => 'v2',
            'status'             => 'completed',
        ];
    }

    public function forSubject(Model $subject): self
    {
        return $this->state([
            'subject_type' => $subject->getMorphClass(),
            'subject_id'   => $subject->getKey(),
        ]);
    }
}
