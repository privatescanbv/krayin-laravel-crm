<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Resource;
use App\Models\ResourceType;
use Illuminate\Support\Facades\Log;

class ResourceSeeder extends BaseSeeder
{
    public function run(): void
    {
        // If resources already exist, skip to avoid duplicates
        if (Resource::count() > 0) {
            $this->command->info('Resources already exist. Skipping seeder.');

            return;
        }
        // external_id from Sugar partner resources (scrm_privatesuite9_pcrm_partnerresources_2.csv), matched by name; null if not in export
        $csvData = [
            ['name' => 'Augusta Cardiologie', 'resource_type' => 'Cardiologie', 'clinic' => 'Ambulante Kardiologie Augusta', 'department' => 'Cardiologie', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => 'aaa84090-f624-1801-0a85-5ecfc8520146'],
            ['name' => 'Augusta Other', 'resource_type' => 'Other', 'clinic' => 'Ambulante Kardiologie Augusta', 'department' => 'Cardiologie', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => null],
            ['name' => 'Ranova-Augusta MRI Scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Evidia - Augusta Klinik', 'department' => 'Radiologie', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => 'c74483dd-818e-c3f9-5302-5e3028755e56'],
            ['name' => 'Ranova-Augusta MRI Scanner 2', 'resource_type' => 'MRI scanner', 'clinic' => 'Evidia - Augusta Klinik', 'department' => 'Radiologie', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => null],
            ['name' => 'Ranova-Augusta CT scanner', 'resource_type' => 'CT scanner', 'clinic' => 'Evidia - Augusta Klinik', 'department' => 'Radiologie', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => 'bc957f7e-b702-5e03-8ea7-5e30682908ba'],
            ['name' => 'Ranova-Augusta Rontgen', 'resource_type' => 'Rontgen', 'clinic' => 'Evidia - Augusta Klinik', 'department' => 'Radiologie', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => null],
            ['name' => 'Evidia-Other', 'resource_type' => 'Other', 'clinic' => 'Evidia - Augusta Klinik', 'department' => 'Radiologie', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => null],
            ['name' => 'Evidia-Artsen', 'resource_type' => 'Artsen', 'clinic' => 'Evidia - Augusta Klinik', 'department' => 'Radiologie', 'is_active' => true, 'allow_outside_availability' => false],
            ['name' => 'Gradus-Artsen', 'resource_type' => 'Artsen', 'clinic' => 'GRADUS - Orthopadie & Unfallchirurgie Dusseldorf', 'department' => 'Standaard', 'is_active' => true, 'allow_outside_availability' => false],
            ['name' => 'Gradus CT PRT', 'resource_type' => 'CT scanner', 'clinic' => 'GRADUS - Orthopadie & Unfallchirurgie Dusseldorf', 'department' => 'Standaard', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => '3fa590ee-b9f8-43c8-199a-679b43fc6ccf'],
            ['name' => 'Procelsio - Arts - consult', 'resource_type' => 'Artsen', 'clinic' => 'Procelsio Clinic GMBH', 'department' => 'Standaard', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => 'a1312700-c716-e217-1e12-5eabf0a87d1a'],
            ['name' => 'Procelsio - Arts - operatie', 'resource_type' => 'Artsen', 'clinic' => 'Procelsio Clinic GMBH', 'department' => 'Standaard', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => 'a50ad2bd-a65e-1a3c-580d-5eabf09f7d6a'],
            ['name' => 'Procelsio - Other', 'resource_type' => 'Other', 'clinic' => 'Procelsio Clinic GMBH', 'department' => 'Standaard', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => null],
            ['name' => 'EvK Eickel- Other', 'resource_type' => 'Other', 'clinic' => 'Radiologie Herne - EvK Eickel', 'department' => 'Standaard', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => null],
            ['name' => 'Radiologie Herne PET Scanner 1 - op aanvraag', 'resource_type' => 'PET CT scanner', 'clinic' => 'Radiologie Herne - EvK Eickel', 'department' => 'Standaard', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => '9b435905-a2f7-d3b8-d8ad-5e983a479d41'],
            ['name' => 'Alta TBS EC', 'resource_type' => 'Artsen', 'clinic' => 'ALTA Klinik GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '5b1c3aa9-9e23-4713-a8bc-4d9dac7596ff'],
            ['name' => 'Alta CT scanner 1 - op aanvraag', 'resource_type' => 'CT scanner', 'clinic' => 'ALTA Klinik GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '65489315-42e7-d153-ad99-4d9b2e96ec3c'],
            ['name' => 'Alta MRI scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'ALTA Klinik GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '935fc738-0c4a-3f8d-bf58-4d91be03922f'],
            ['name' => 'Alta Cardiologie - op aanvraag', 'resource_type' => 'Cardiologie', 'clinic' => 'ALTA Klinik GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => 'c2e98505-7e4c-0651-6791-4f15322b0165'],
            ['name' => 'Dr. Jan de Letter - consultatie', 'resource_type' => 'Artsen', 'clinic' => 'AZ St. Jan Brugge', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '4a81e44a-7655-a22f-6fd9-4ece309cf0bc'],
            ['name' => 'AZ. St. Jan CT Scanner 1', 'resource_type' => 'CT scanner', 'clinic' => 'AZ St. Jan Brugge', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => 'db12dc05-5d1f-46ae-9516-4e71f9482c66'],
            ['name' => 'Dr. Jan de Letter - behandeling', 'resource_type' => 'Artsen', 'clinic' => 'AZ St. Jan Brugge', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => 'e81b94ea-47a7-803f-5bc0-4e71fae384f1'],
            ['name' => 'Bel Etage - Arts - operatie', 'resource_type' => 'Artsen', 'clinic' => 'Clinic Bel Etage', 'department' => 'Standaard', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => '4a70248f-714a-5a11-5de8-5f36333e2520'],
            ['name' => 'Euregio Cardiologie - op aanvraag', 'resource_type' => 'Cardiologie', 'clinic' => 'Euregio-Klinik GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '1c281063-053d-8d83-d400-4f153365e2af'],
            ['name' => 'Euregio MRI scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Euregio-Klinik GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '4a78a631-a576-fd7c-4dde-4d9da6c50263'],
            ['name' => 'Euregio CT scanner 1 - op aanvraag', 'resource_type' => 'CT scanner', 'clinic' => 'Euregio-Klinik GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '5c72dbb7-4158-4663-03e6-4ec16b88863d'],
            ['name' => 'Euregio Dr. - consultatie', 'resource_type' => 'Artsen', 'clinic' => 'Euregio-Klinik GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '7b5e14cd-6901-96bf-27ef-52161b90fcf7'],
            ['name' => 'Euregio TBS EC', 'resource_type' => 'Artsen', 'clinic' => 'Euregio-Klinik GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '9914ed87-9802-bd43-8c87-4d9daf96f037'],
            ['name' => 'Wesel MRI scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Evangelisches Krankenhaus Wesel GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '224488f0-f91e-77c3-6d81-4d9d7416f80d'],
            ['name' => 'Wesel CT scanner 1 - op aanvraag', 'resource_type' => 'CT scanner', 'clinic' => 'Evangelisches Krankenhaus Wesel GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '29346324-63ef-2269-dc53-4ec169a5f40f'],
            ['name' => 'Wesel Cardiologie - op aanvraag', 'resource_type' => 'Cardiologie', 'clinic' => 'Evangelisches Krankenhaus Wesel GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '4e280df6-175a-dab4-c62e-4f1533f59790'],
            ['name' => 'Wesel Gastro Enterologie', 'resource_type' => 'Artsen', 'clinic' => 'Evangelisches Krankenhaus Wesel GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '718c3625-5370-e932-cc4f-4ec3ba420f89'],
            ['name' => 'GrandArc CT scanner', 'resource_type' => 'CT scanner', 'clinic' => 'Medical Center Düsseldorf', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '21c211ec-2a2f-a97e-f78a-5672bfcd386c'],
            ['name' => 'GrandArc scintigrafie en overig', 'resource_type' => 'Artsen', 'clinic' => 'Medical Center Düsseldorf', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '28080372-6fe1-6708-f4c3-5ca21092886f'],
            ['name' => 'GrandArc Gastro Enterologie', 'resource_type' => 'Artsen', 'clinic' => 'Medical Center Düsseldorf', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '760aca22-8932-fbeb-63b6-56729b43d0bb'],
            ['name' => 'GrandArc MRI Scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Medical Center Düsseldorf', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '9cab4641-28ff-2bf4-7e30-5672722c5b6f'],
            ['name' => 'GrandArc Cardiologie - op aanvraag', 'resource_type' => 'Cardiologie', 'clinic' => 'Medical Center Düsseldorf', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => 'c68feecf-a0a2-7999-04d7-5672a3cc7de2'],
            ['name' => 'GrandArc MRI Esprit', 'resource_type' => 'MRI scanner', 'clinic' => 'Medical Center Düsseldorf', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => 'c6fdce40-dd8a-5972-a7b8-56a1e5d5d9ad'],
            ['name' => 'ONZ_Reck - Arts - operatie', 'resource_type' => 'Artsen', 'clinic' => 'ONZ Recklinghausen', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '19fc1362-257f-2c99-e093-5eb016178e61'],
            ['name' => 'ONZ_Reck - Arts - consult', 'resource_type' => 'Artsen', 'clinic' => 'ONZ Recklinghausen', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => 'a5c1de56-8d61-fa0b-53b0-5eb015d594ec'],
            ['name' => 'Pradus Cardiologie', 'resource_type' => 'Cardiologie', 'clinic' => 'Pradus Medical Center Düsseldorf', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => 'be6c8870-f9ca-6a26-9b05-5cf913b5bffd'],
            ['name' => 'Preventicum CT scanner 1 - op aanvraag', 'resource_type' => 'CT scanner', 'clinic' => 'Preventicum', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '748207a7-0024-5c84-eb27-4f54ae6ed954'],
            ['name' => 'Preventicum MRI scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Preventicum', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '8ac902bc-431a-d0e2-fe30-4ea1e0d17ec7'],
            ['name' => 'Preventicum Cardiologie - op aanvraag', 'resource_type' => 'Cardiologie', 'clinic' => 'Preventicum', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => 'b00149bb-e76c-5a13-ae5b-4f55ce2472fc'],
            ['name' => 'Dusseldorf Mitte CT scanner', 'resource_type' => 'CT scanner', 'clinic' => 'Radiologie Düsseldorf Mitte', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '4f429c74-c13b-698b-7805-5cf9048b74fc'],
            ['name' => 'Dusseldorf Mitte MRI Scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Radiologie Düsseldorf Mitte', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '69bca484-f560-11a0-139f-5cf90499ee95'],
            ['name' => 'Radionuk PET Scanner 1 - op aanvraag', 'resource_type' => 'PET CT scanner', 'clinic' => 'Radionuk Essen', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '5b949be7-688a-e08c-ca9a-4f9a9024dc30'],
            ['name' => 'Radionuk Dr. - consultatie', 'resource_type' => 'Artsen', 'clinic' => 'Radionuk Essen', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '7e3bdc18-3fad-59fa-8df4-523af7a4b7b9'],
            ['name' => 'Schön Kliniek - Arts - consult', 'resource_type' => 'Artsen', 'clinic' => 'Schön Klinik Düsseldorf', 'department' => 'Standaard', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => '370bc96d-aa16-072b-d115-63403ffb4d86'],
            ['name' => 'Schön Kliniek - Arts - operatie', 'resource_type' => 'Artsen', 'clinic' => 'Schön Klinik Düsseldorf', 'department' => 'Standaard', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => '43dbb5d4-c7a8-6106-a3bb-63286f46af3d'],
            ['name' => 'Schön Kliniek - CT scanner', 'resource_type' => 'CT scanner', 'clinic' => 'Schön Klinik Düsseldorf', 'department' => 'Standaard', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => '64c599f5-d791-b732-95fc-633e840f5ff9'],
            ['name' => 'Radiologie Gronau PET Scanner 1 - op aanvraag', 'resource_type' => 'PET CT scanner', 'clinic' => 'St. Antonius-Hospital Gronau GmbH', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '73285318-bb00-5265-fac8-5f71a3f5e69f'],
            ['name' => 'Maria-Hilf Cardiologie - op aanvraag', 'resource_type' => 'Cardiologie', 'clinic' => 'Maria-Hilf Krankenhaus', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '4a4313e8-79fc-c4ce-1036-50867b49eb4a'],
            ['name' => 'Maria-Hilf Dr. - behandeling', 'resource_type' => 'Artsen', 'clinic' => 'Maria-Hilf Krankenhaus', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '64e9086c-942a-f7b7-e044-50867b3ffd98'],
            ['name' => 'Maria-Hilf Gastro Enterologie', 'resource_type' => 'Artsen', 'clinic' => 'Maria-Hilf Krankenhaus', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '77d28e87-758d-1533-f07c-50867c6c7810'],
            ['name' => 'Maria-Hilf Dr. - consultatie', 'resource_type' => 'Artsen', 'clinic' => 'Maria-Hilf Krankenhaus', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => '8d4212ad-f690-19ec-46f3-50867bde7534'],
            ['name' => 'Maria-Hilf CT Scanner 1 - op aanvraag', 'resource_type' => 'CT scanner', 'clinic' => 'Maria-Hilf Krankenhaus', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => 'c3ac6c97-496f-ab28-f0ae-50867a3f3668'],
            ['name' => 'Maria-Hilf MRI Scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Maria-Hilf Krankenhaus', 'department' => 'Standaard', 'is_active' => false, 'allow_outside_availability' => true, 'external_id' => 'e165eaca-359c-1000-5243-508679a8815c'],
            ['name' => 'Intertran Other', 'resource_type' => 'Other', 'clinic' => 'Intertran', 'department' => 'Standaard', 'is_active' => true, 'allow_outside_availability' => true, 'external_id' => null],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($csvData as $row) {
            // Look up ResourceType by name
            $resourceType = ResourceType::where('name', $row['resource_type'])->first();
            if (! $resourceType) {
                $this->command->warn("ResourceType not found: {$row['resource_type']} for resource {$row['name']}");
                Log::warning("ResourceType not found: {$row['resource_type']} for resource {$row['name']}");
                $skipped++;

                continue;
            }

            // Look up Clinic by name
            $clinic = Clinic::where('name', $row['clinic'])->first();
            if (! $clinic) {
                $this->command->warn("Clinic not found: {$row['clinic']} for resource {$row['name']}");
                Log::warning("Clinic not found: {$row['clinic']} for resource {$row['name']}");
                $skipped++;

                continue;
            }

            // Look up ClinicDepartment by name within the clinic
            $dept = ClinicDepartment::where('clinic_id', $clinic->id)
                ->where('name', $row['department'])
                ->first();

            if (! $dept) {
                $this->command->warn("Department '{$row['department']}' not found in clinic '{$clinic->name}' for resource {$row['name']}");
                Log::warning("Department '{$row['department']}' not found in clinic '{$clinic->name}' for resource {$row['name']}");
                $skipped++;

                continue;
            }

            // Create resource
            Resource::create([
                'name'                         => $row['name'],
                'resource_type_id'             => $resourceType->id,
                'clinic_id'                    => $clinic->id,
                'clinic_department_id'         => $dept->id,
                'is_active'                    => $row['is_active'],
                'allow_outside_availability'   => $row['allow_outside_availability'],
                'external_id'                  => $row['external_id'] ?? null,
            ]);

            $created++;
        }

        $this->command->info("ResourceSeeder completed: {$created} resources created, {$skipped} skipped.");
    }
}
