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
                'name'   => 'Hernia Kliniek Amsterdam',
                'emails' => [['value' => 'amsterdam@hernia.nl', 'label' => 'work']],
                'phones' => [['value' => '+31201234567', 'label' => 'work']],
            ],
            [
                'name'   => 'Hernia Kliniek Rotterdam',
                'emails' => [['value' => 'rotterdam@hernia.nl', 'label' => 'work']],
                'phones' => [['value' => '+31101234567', 'label' => 'work']],
            ],
            [
                'name'   => 'Privatescan Utrecht',
                'emails' => [['value' => 'utrecht@privatescan.nl', 'label' => 'work']],
                'phones' => [['value' => '+31301234567', 'label' => 'work']],
            ],
        ];

        foreach ($defaults as $data) {
            Clinic::create($data);
        }
    }
}