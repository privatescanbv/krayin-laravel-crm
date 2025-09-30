<?php

namespace Database\Seeders;

use App\Models\Clinic;

class ClinicSeeder extends BaseSeeder
{
    public function run(): void
    {
        $this->truncateTables(['clinics']);

        $defaults = [
            [
                'name'   => 'Amsterdam Medical Center',
                'emails' => [['value' => 'info@amsterdam-medical.nl', 'label' => 'work']],
                'phones' => [['value' => '+31201234567', 'label' => 'work']],
            ],
            [
                'name'   => 'Rotterdam Diagnostics',
                'emails' => [['value' => 'info@rotterdam-diagnostics.nl', 'label' => 'work']],
                'phones' => [['value' => '+31102345678', 'label' => 'work']],
            ],
            [
                'name'   => 'Utrecht Health Center',
                'emails' => [['value' => 'info@utrecht-health.nl', 'label' => 'work']],
                'phones' => [['value' => '+31303456789', 'label' => 'work']],
            ],
        ];

        foreach ($defaults as $data) {
            Clinic::create($data);
        }
    }
}