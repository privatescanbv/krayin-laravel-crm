<?php

namespace Database\Factories;

use App\Models\LeadAiSummary;
use App\Models\LeadAiSummaryGeneration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadAiSummaryGeneration>
 */
class LeadAiSummaryGenerationFactory extends Factory
{
    protected $model = LeadAiSummaryGeneration::class;

    public function definition(): array
    {
        return [
            'lead_ai_summary_id' => LeadAiSummary::factory(),
            'lead_id'            => fn (array $attributes) => LeadAiSummary::find($attributes['lead_ai_summary_id'])->lead_id,
            'status'             => 'completed',
            'input_hash'         => hash('sha256', $this->faker->uuid()),
            'context_snapshot'   => [],
            'parsed_response'    => [],
            'model'              => 'test-model',
            'prompt_version'     => 'v1',
            'duration_ms'        => 100,
            'started_at'         => now()->subSecond(),
            'completed_at'       => now(),
        ];
    }
}
