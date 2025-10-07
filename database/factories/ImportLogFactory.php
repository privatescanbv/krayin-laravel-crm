<?php

namespace Database\Factories;

use App\Models\ImportLog;
use App\Models\ImportRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportLogFactory extends Factory
{
    protected $model = ImportLog::class;

    public function definition(): array
    {
        return [
            'import_run_id' => ImportRun::factory(),
            'level'         => $this->faker->randomElement(['error', 'warning', 'info']),
            'message'       => $this->faker->sentence(),
            'context'       => [
                'record_id' => $this->faker->uuid(),
                'error'     => $this->faker->optional()->sentence(),
            ],
            'record_id'     => $this->faker->optional()->uuid(),
        ];
    }
}