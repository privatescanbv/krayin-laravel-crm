<?php

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    public function definition(): array
    {
        return [
            'key'                     => null,
            'name'                    => 'Test '.$this->faker->unique()->words(2, true),
            'description'             => $this->faker->optional()->sentence(),
            'show_in_clinic_portal'   => true,
            'show_in_patient_portal'  => true,
            'sort_order'              => 0,
        ];
    }
}
