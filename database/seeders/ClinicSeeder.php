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
        $clinics = [
            [
                'external_id'                   => 'db0c47c3-aaf8-ce22-a112-4d9abef87a8f',
                'is_active'                     => false,
                'name'                          => 'ALTA Klinik GmbH',
                'registration_form_clinic_name' => 'Alta',
            ],
            [
                'external_id'                   => 'ae8ea8a1-95c4-d665-4373-5ecfc67d6096',
                'is_active'                     => true,
                'name'                          => 'Ambulante Kardiologie Augusta',
                'registration_form_clinic_name' => 'Amb. Kardio Augusta',
            ],
            [
                'external_id'                   => '85e31add-d6bd-092c-f009-4e71fad20c0f',
                'is_active'                     => false,
                'name'                          => 'Apollo Arthotel Brugge',
                'registration_form_clinic_name' => 'Apollo Hotel',
            ],
            [
                'external_id'                   => '4006e894-d718-f009-4b65-4e71f864a02d',
                'is_active'                     => false,
                'name'                          => 'AZ St. Jan Brugge',
                'registration_form_clinic_name' => 'AZ St. Jan',
            ],
            [
                'external_id'                   => 'cd901e3d-8015-d910-5f64-5f36336bcfc8',
                'is_active'                     => false,
                'name'                          => 'Clinic Bel Etage',
                'registration_form_clinic_name' => 'Bel Etage',
            ],
            [
                'external_id'                   => '6e7519bf-aa5f-90e1-e18e-4d9da38d7e96',
                'is_active'                     => false,
                'name'                          => 'Euregio-Klinik GmbH',
                'registration_form_clinic_name' => 'Euregio',
            ],
            [
                'external_id'                   => 'da548322-a3a3-672a-2dd0-4d9adaf29b78',
                'is_active'                     => false,
                'name'                          => 'Evangelisches Krankenhaus Wesel GmbH',
                'registration_form_clinic_name' => 'Wesel',
            ],
            [
                'external_id'                   => '3e31d364-93f7-837f-6e7c-5e3027d93466',
                'is_active'                     => true,
                'name'                          => 'Evidia - Augusta Klinik',
                'registration_form_clinic_name' => 'Evidia - Augusta Klinik',
            ],
            [
                'external_id'                   => 'f90e0ef6-e600-b56c-b9af-679b4021bc10',
                'is_active'                     => true,
                'name'                          => 'GRADUS - Orthopadie & Unfallchirurgie Dusseldorf',
                'registration_form_clinic_name' => 'Gradus Dusseldorf',
            ],
            [
                'external_id'                   => '847b31c2-60fd-12f8-3302-4e6a176653bd',
                'is_active'                     => true,
                'name'                          => 'Intertran',
                'registration_form_clinic_name' => 'Intertran',
            ],
            [
                'external_id'                   => '65a7b039-8fd3-c49e-1029-567271e9f437',
                'is_active'                     => false,
                'name'                          => 'Medical Center Düsseldorf',
                'registration_form_clinic_name' => 'Luisenkrankenhaus Dusseldorf',
            ],
            [
                'external_id'                   => '839fbf0b-d86b-2aa8-b9e2-5eb01973e3b3',
                'is_active'                     => false,
                'name'                          => 'ONZ Datteln',
                'registration_form_clinic_name' => 'ONZ Datteln',
            ],
            [
                'external_id'                   => '8ec053e9-9815-903a-f50b-5eb015d338a6',
                'is_active'                     => false,
                'name'                          => 'ONZ Recklinghausen',
                'registration_form_clinic_name' => 'ONZ Recklinghausen',
            ],
            [
                'external_id'                   => 'ccdb4356-923a-8408-1e4a-5cf91286a7c9',
                'is_active'                     => false,
                'name'                          => 'Pradus Medical Center Düsseldorf',
                'registration_form_clinic_name' => 'Pradus Dusseldorf',
            ],
            [
                'external_id'                   => '6fd1b389-dcb8-2718-c908-4e6a19367911',
                'is_active'                     => false,
                'name'                          => 'Preventicum',
                'registration_form_clinic_name' => 'Preventicum',
            ],
            [
                'external_id'                   => '619c39c5-c437-6e2c-17c2-5eabedb46fe4',
                'is_active'                     => true,
                'name'                          => 'Procelsio Clinic GMBH',
                'registration_form_clinic_name' => 'Procelsio',
            ],
            [
                'external_id'                   => '39bd339d-0b40-4a80-1012-5cf902f1960d',
                'is_active'                     => false,
                'name'                          => 'Radiologie Düsseldorf Mitte',
                'registration_form_clinic_name' => 'Dusseldorf Mitte',
            ],
            [
                'external_id'                   => '4b7dd6e9-2824-b1fe-d515-5e9839813d42',
                'is_active'                     => true,
                'name'                          => 'Radiologie Herne - EvK Eickel',
                'registration_form_clinic_name' => 'Rad. Herne - EvK Eickel',
            ],
            [
                'external_id'                   => 'c4de98b0-4d3e-02c4-4b80-4ea2a2ad376a',
                'is_active'                     => false,
                'name'                          => 'Radionuk Essen',
                'registration_form_clinic_name' => 'Radionuk Essen',
            ],
            [
                'external_id'                   => '7a30fda1-5764-fb0a-12c7-63286b29f7f7',
                'is_active'                     => false,
                'name'                          => 'Schön Klinik Düsseldorf',
                'registration_form_clinic_name' => 'Schön Kliniek',
            ],
            [
                'external_id'                   => 'd0257a7c-7b68-b517-25c6-5f71a142b666',
                'is_active'                     => false,
                'name'                          => 'St. Antonius-Hospital Gronau GmbH',
                'registration_form_clinic_name' => 'St. Antonius-Hospital',
            ],
            [
                'external_id'                   => '768753bc-2f1c-9651-99e7-50867822c36d',
                'is_active'                     => false,
                'name'                          => 'Maria-Hilf Krankenhaus',
                'registration_form_clinic_name' => 'Maria-Hilf Krankenhaus',
            ],
        ];

        foreach ($clinics as $clinicData) {
            // Use name as unique key according to clinics.clinics_name_unique
            Clinic::updateOrCreate(
                ['name' => $clinicData['name']],
                [
                    'external_id' => $clinicData['external_id'],
                    'registration_form_clinic_name' => $clinicData['registration_form_clinic_name'],
                ]
            );
        }
    }
}
