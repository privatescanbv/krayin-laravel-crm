<?php

namespace Database\Factories;

use App\Models\AssessmentOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentOutcomeFactory extends Factory
{
    protected $model = AssessmentOutcome::class;

    public function definition(): array
    {
        $label = $this->faker->unique()->words(2, true);

        return [
            'code'              => str_replace(' ', '_', $label),
            'label'             => $label,
            'is_surgery_advice' => true,
            'sort_order'        => 100,
        ];
    }
}
