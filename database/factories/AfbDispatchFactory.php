<?php

namespace Database\Factories;

use App\Enums\AfbDispatchStatus;
use App\Enums\AfbDispatchType;
use App\Models\AfbDispatch;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AfbDispatch>
 */
class AfbDispatchFactory extends Factory
{
    protected $model = AfbDispatch::class;

    public function definition(): array
    {
        $clinic = Clinic::factory()->create();

        return [
            'clinic_id'            => $clinic->id,
            'clinic_department_id' => ClinicDepartment::factory()->create(['clinic_id' => $clinic->id])->id,
            'type'                 => AfbDispatchType::INDIVIDUAL->value,
            'status'               => AfbDispatchStatus::SUCCESS->value,
            'sent_at'              => now(),
            'last_attempt_at'      => now(),
            'attempt'              => 1,
        ];
    }
}
