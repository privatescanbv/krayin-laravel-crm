<?php

namespace Database\Factories;

use App\Models\ImportRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportRunFactory extends Factory
{
    protected $model = ImportRun::class;

    public function definition(): array
    {
        return [
            'started_at'         => $this->faker->dateTimeBetween('-1 week', 'now'),
            'completed_at'       => $this->faker->optional()->dateTimeBetween('now', '+1 hour'),
            'status'             => $this->faker->randomElement(['running', 'completed', 'failed']),
            'import_type'        => $this->faker->randomElement(['leads', 'persons', 'users', 'email-attachments']),
            'records_processed'  => $this->faker->numberBetween(0, 1000),
            'records_imported'   => $this->faker->numberBetween(0, 1000),
            'records_skipped'    => $this->faker->numberBetween(0, 100),
            'records_errored'    => $this->faker->numberBetween(0, 50),
        ];
    }
}
