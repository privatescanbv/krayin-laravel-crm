<?php

namespace Database\Seeders;

use App\Models\Clinic;

class ClinicSeeder extends BaseSeeder
{
    public function run(): void
    {
        // If clinics already exist (seeded by installer or previous runs), skip to avoid duplicates
        if (Clinic::count() > 0) {
            return;
        }

        Clinic::updateOrCreate(
            ['name' => 'Default Clinic'],
            [
                'emails' => ['default.clinic@example.com'],
                'phones' => ['+31 20 123 4567'],
            ]
        );

        Clinic::updateOrCreate(
            ['name' => 'Second Clinic'],
            [
                'emails' => ['second.clinic@example.com'],
                'phones' => ['+31 20 987 6543'],
            ]
        );
    }
}
