<?php

namespace Database\Factories;

use App\Models\Resource;
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
        $periodStart = $this->faker->dateTimeBetween('+0 days', '+1 month');
        $periodEnd = (clone $periodStart)->modify('+7 days');

        return [
            'resource_id'         => Resource::factory(),
            'period_start'        => $periodStart,
            'period_end'          => $periodEnd,
            'weekday_time_blocks' => [
                1 => [['from' => '08:00', 'to' => '12:00'], ['from' => '13:00', 'to' => '17:00']],
                2 => [['from' => '08:00', 'to' => '12:00'], ['from' => '13:00', 'to' => '17:00']],
                3 => [['from' => '08:00', 'to' => '12:00'], ['from' => '13:00', 'to' => '17:00']],
                4 => [['from' => '08:00', 'to' => '12:00'], ['from' => '13:00', 'to' => '17:00']],
                5 => [['from' => '08:00', 'to' => '12:00'], ['from' => '13:00', 'to' => '17:00']],
            ],
            'available' => true,
            'notes'     => $this->faker->optional()->sentence(),
        ];
    }
}
