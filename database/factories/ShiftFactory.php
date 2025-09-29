<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\Clinic;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+0 days', '+1 month');
        $end = (clone $start)->modify('+4 hours');

        return [
            'clinic_id'  => Clinic::factory(),
            'resource_id' => Resource::factory(),
            'starts_at'   => $start,
            'ends_at'     => $end,
            'notes'       => $this->faker->optional()->sentence(),
        ];
    }
}
