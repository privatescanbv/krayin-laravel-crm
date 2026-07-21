<?php

namespace Database\Factories;

use App\Models\AiSummary;
use App\Models\AiSummaryGeneration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSummaryGeneration>
 */
class AiSummaryGenerationFactory extends Factory
{
    protected $model = AiSummaryGeneration::class;

    public function definition(): array
    {
        return [
            'ai_summary_id'    => AiSummary::factory(),
            'subject_type'     => fn (array $attributes) => AiSummary::find($attributes['ai_summary_id'])->subject_type,
            'subject_id'       => fn (array $attributes) => AiSummary::find($attributes['ai_summary_id'])->subject_id,
            'status'           => 'completed',
            'input_hash'       => hash('sha256', $this->faker->uuid()),
            'context_snapshot' => [],
            'parsed_response'  => [],
            'model'            => 'test-model',
            'prompt_version'   => 'v1',
            'duration_ms'      => 100,
            'started_at'       => now()->subSecond(),
            'completed_at'     => now(),
        ];
    }
}
