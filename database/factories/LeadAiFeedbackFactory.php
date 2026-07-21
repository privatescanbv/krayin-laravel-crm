<?php

namespace Database\Factories;

use App\Models\LeadAiFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

/**
 * @extends Factory<LeadAiFeedback>
 */
class LeadAiFeedbackFactory extends Factory
{
    protected $model = LeadAiFeedback::class;

    public function definition(): array
    {
        return [
            'lead_id'   => Lead::factory(),
            'user_id'   => User::factory(),
            'feedback'  => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
