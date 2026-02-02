<?php

namespace Database\Factories;

use App\Enums\NotificationReferenceType;
use App\Models\PatientNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientNotification>
 */
class PatientNotificationFactory extends Factory
{
    protected $model = PatientNotification::class;

    public function definition(): array
    {
        $dismissable = $this->faker->boolean(70);

        return [
            'patient_id'               => 1,
            'type'                     => 'document',
            'dismissable'              => $dismissable,
            'title'                    => $this->faker->sentence(6),
            'summary'                  => $this->faker->boolean(60) ? $this->faker->text(180) : null,
            'reference_type'           => $this->faker->randomElement(NotificationReferenceType::cases()),
            'reference_id'             => $this->faker->numberBetween(1, 5000),
            'read_at'                  => null,
            'dismissed_at'             => null,
            'expires_at'               => $this->faker->boolean(20) ? $this->faker->dateTimeBetween('now', '+30 days') : null,
            'last_notified_by_email_at'=> null,
        ];
    }
}
