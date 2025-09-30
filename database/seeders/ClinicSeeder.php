<?php

namespace Database\Seeders;

use App\Models\Clinic;

class ClinicSeeder extends BaseSeeder
{
    public function run(): void
    {
        $this->truncateTables(['clinics']);

        Clinic::firstOrCreate(
            ['name' => 'Default Clinic'],
            [
                'emails' => ['default.clinic@example.com'],
                'phones' => ['+31 20 123 4567'],
            ]
        );

        Clinic::firstOrCreate(
            ['name' => 'Second Clinic'],
            [
                'emails' => ['second.clinic@example.com'],
                'phones' => ['+31 20 987 6543'],
            ]
        );
    }
}
