<?php

namespace Database\Factories;

use App\Models\AiFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

/**
 * @extends Factory<AiFeedback>
 */
class AiFeedbackFactory extends Factory
{
    protected $model = AiFeedback::class;

    public function definition(): array
    {
        return [
            'subject_type' => (new Lead)->getMorphClass(),
            'subject_id'   => Lead::factory(),
            'user_id'      => User::factory(),
            'feedback'     => $this->faker->sentence(),
            'is_active'    => true,
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
