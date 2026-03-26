<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\ClinicDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicDepartmentFactory extends Factory
{
    protected $model = ClinicDepartment::class;

    public function definition(): array
    {
        $clinic = Clinic::query()->inRandomOrder()->first() ?? Clinic::factory()->create();

        return [
            'clinic_id' => $clinic->id,
            'name'      => $this->faker->unique()->word(),
        ];
    }
}
