<?php

namespace Database\Factories;

use App\Enums\PatientMessageSenderType;
use App\Models\PatientMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientMessage>
 */
class PatientMessageFactory extends Factory
{
    protected $model = PatientMessage::class;

    public function definition(): array
    {
        return [
            'person_id'   => 1,
            'sender_type' => PatientMessageSenderType::STAFF,
            'sender_id'   => null,
            'body'        => $this->faker->sentence(),
            'is_read'     => false,
            'activity_id' => null,
        ];
    }
}
