<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Resource;
use App\Models\ResourceType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Resource $resource): void {
            $clinicId = $resource->getAttribute('clinic_id');

            if ($clinicId !== null) {
                $currentDept = $resource->clinic_department_id
                    ? ClinicDepartment::find($resource->clinic_department_id)
                    : null;

                if (! $currentDept || (int) $currentDept->clinic_id !== (int) $clinicId) {
                    $currentDept = ClinicDepartment::where('clinic_id', $clinicId)->inRandomOrder()->first()
                        ?? ClinicDepartment::factory()->create(['clinic_id' => $clinicId]);
                }

                $resource->clinic_department_id = $currentDept->id;
            }

            unset($resource->clinic_id);
        });
    }

    public function definition(): array
    {
        $resourceType = ResourceType::query()->inRandomOrder()->first() ?? ResourceType::factory()->create();
        $clinic = Clinic::query()->inRandomOrder()->first() ?? Clinic::factory()->create();
        $dept = ClinicDepartment::where('clinic_id', $clinic->id)->inRandomOrder()->first()
            ?? ClinicDepartment::factory()->create(['clinic_id' => $clinic->id]);

        return [
            'resource_type_id'     => $resourceType->id,
            'clinic_department_id' => $dept->id,
            'name'                 => $this->faker->unique()->words(2, true),
        ];
    }
}
