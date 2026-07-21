<?php

namespace Database\Factories;

use App\Models\LeadAiSummary;
use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Lead\Models\Lead;

/**
 * @extends Factory<LeadAiSummary>
 */
class LeadAiSummaryFactory extends Factory
{
    protected $model = LeadAiSummary::class;

    public function definition(): array
    {
        return [
            'lead_id'            => Lead::factory(),
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
}
