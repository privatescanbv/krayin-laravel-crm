<?php

namespace Database\Seeders;

use App\Models\Clinic;
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
        $csvData = [
            ['name' => 'Augusta Cardiologie', 'resource_type' => 'Cardiologie', 'clinic' => 'Ambulante Kardiologie Augusta', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Augusta Other', 'resource_type' => 'Other', 'clinic' => 'Ambulante Kardiologie Augusta', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Ranova-Augusta MRI Scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Evidia - Augusta Klinik', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Ranova-Augusta CT scanner', 'resource_type' => 'CT scanner', 'clinic' => 'Evidia - Augusta Klinik', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Ranova-Augusta Rontgen', 'resource_type' => 'Rontgen', 'clinic' => 'Evidia - Augusta Klinik', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Evidia-Other', 'resource_type' => 'Other', 'clinic' => 'Evidia - Augusta Klinik', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Evidia-Artsen', 'resource_type' => 'Artsen', 'clinic' => 'Evidia - Augusta Klinik', 'is_active' => true, 'allow_outside_availability' => false],
            ['name' => 'Gradus-Artsen', 'resource_type' => 'Artsen', 'clinic' => 'GRADUS - Orthopadie & Unfallchirurgie Dusseldorf', 'is_active' => true, 'allow_outside_availability' => false],
            ['name' => 'Gradus CT PRT', 'resource_type' => 'CT scanner', 'clinic' => 'GRADUS - Orthopadie & Unfallchirurgie Dusseldorf', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Procelsio - Arts - consult', 'resource_type' => 'Artsen', 'clinic' => 'Procelsio Clinic GMBH', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Procelsio - Arts - operatie', 'resource_type' => 'Artsen', 'clinic' => 'Procelsio Clinic GMBH', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Procelsio - Other', 'resource_type' => 'Other', 'clinic' => 'Procelsio Clinic GMBH', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'EvK Eickel- Other', 'resource_type' => 'Other', 'clinic' => 'Radiologie Herne - EvK Eickel', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Radiologie Herne PET Scanner 1 - op aanvraag', 'resource_type' => 'PET CT scanner', 'clinic' => 'Radiologie Herne - EvK Eickel', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Alta TBS EC', 'resource_type' => 'Artsen', 'clinic' => 'ALTA Klinik GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Alta CT scanner 1 - op aanvraag', 'resource_type' => 'CT scanner', 'clinic' => 'ALTA Klinik GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Alta MRI scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'ALTA Klinik GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Alta Cardiologie - op aanvraag', 'resource_type' => 'Cardiologie', 'clinic' => 'ALTA Klinik GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Dr. Jan de Letter - consultatie', 'resource_type' => 'Artsen', 'clinic' => 'AZ St. Jan Brugge', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'AZ. St. Jan CT Scanner 1', 'resource_type' => 'CT scanner', 'clinic' => 'AZ St. Jan Brugge', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Dr. Jan de Letter - behandeling', 'resource_type' => 'Artsen', 'clinic' => 'AZ St. Jan Brugge', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Bel Etage - Arts - operatie', 'resource_type' => 'Artsen', 'clinic' => 'Clinic Bel Etage', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Euregio Cardiologie - op aanvraag', 'resource_type' => 'Cardiologie', 'clinic' => 'Euregio-Klinik GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Euregio MRI scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Euregio-Klinik GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Euregio CT scanner 1 - op aanvraag', 'resource_type' => 'CT scanner', 'clinic' => 'Euregio-Klinik GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Euregio Dr. - consultatie', 'resource_type' => 'Artsen', 'clinic' => 'Euregio-Klinik GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Euregio TBS EC', 'resource_type' => 'Artsen', 'clinic' => 'Euregio-Klinik GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Wesel MRI scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Evangelisches Krankenhaus Wesel GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Wesel CT scanner 1 - op aanvraag', 'resource_type' => 'CT scanner', 'clinic' => 'Evangelisches Krankenhaus Wesel GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Wesel Cardiologie - op aanvraag', 'resource_type' => 'Cardiologie', 'clinic' => 'Evangelisches Krankenhaus Wesel GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Wesel Gastro Enterologie', 'resource_type' => 'Artsen', 'clinic' => 'Evangelisches Krankenhaus Wesel GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'GrandArc CT scanner', 'resource_type' => 'CT scanner', 'clinic' => 'Medical Center Düsseldorf', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'GrandArc scintigrafie en overig', 'resource_type' => 'Artsen', 'clinic' => 'Medical Center Düsseldorf', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'GrandArc Gastro Enterologie', 'resource_type' => 'Artsen', 'clinic' => 'Medical Center Düsseldorf', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'GrandArc MRI Scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Medical Center Düsseldorf', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'GrandArc Cardiologie - op aanvraag', 'resource_type' => 'Cardiologie', 'clinic' => 'Medical Center Düsseldorf', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'GrandArc MRI Esprit', 'resource_type' => 'MRI scanner', 'clinic' => 'Medical Center Düsseldorf', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'ONZ_Reck - Arts - operatie', 'resource_type' => 'Artsen', 'clinic' => 'ONZ Recklinghausen', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'ONZ_Reck - Arts - consult', 'resource_type' => 'Artsen', 'clinic' => 'ONZ Recklinghausen', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Pradus Cardiologie', 'resource_type' => 'Cardiologie', 'clinic' => 'Pradus Medical Center Düsseldorf', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Preventicum CT scanner 1 - op aanvraag', 'resource_type' => 'CT scanner', 'clinic' => 'Preventicum', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Preventicum MRI scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Preventicum', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Preventicum Cardiologie - op aanvraag', 'resource_type' => 'Cardiologie', 'clinic' => 'Preventicum', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Dusseldorf Mitte CT scanner', 'resource_type' => 'CT scanner', 'clinic' => 'Radiologie Düsseldorf Mitte', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Dusseldorf Mitte MRI Scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Radiologie Düsseldorf Mitte', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Radionuk PET Scanner 1 - op aanvraag', 'resource_type' => 'PET CT scanner', 'clinic' => 'Radionuk Essen', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Radionuk Dr. - consultatie', 'resource_type' => 'Artsen', 'clinic' => 'Radionuk Essen', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Schön Kliniek - Arts - consult', 'resource_type' => 'Artsen', 'clinic' => 'Schön Klinik Düsseldorf', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Schön Kliniek - Arts - operatie', 'resource_type' => 'Artsen', 'clinic' => 'Schön Klinik Düsseldorf', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Schön Kliniek - CT scanner', 'resource_type' => 'CT scanner', 'clinic' => 'Schön Klinik Düsseldorf', 'is_active' => true, 'allow_outside_availability' => true],
            ['name' => 'Radiologie Gronau PET Scanner 1 - op aanvraag', 'resource_type' => 'PET CT scanner', 'clinic' => 'St. Antonius-Hospital Gronau GmbH', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Maria-Hilf Cardiologie - op aanvraag', 'resource_type' => 'Cardiologie', 'clinic' => 'Maria-Hilf Krankenhaus', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Maria-Hilf Dr. - behandeling', 'resource_type' => 'Artsen', 'clinic' => 'Maria-Hilf Krankenhaus', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Maria-Hilf Gastro Enterologie', 'resource_type' => 'Artsen', 'clinic' => 'Maria-Hilf Krankenhaus', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Maria-Hilf Dr. - consultatie', 'resource_type' => 'Artsen', 'clinic' => 'Maria-Hilf Krankenhaus', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Maria-Hilf CT Scanner 1 - op aanvraag', 'resource_type' => 'CT scanner', 'clinic' => 'Maria-Hilf Krankenhaus', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Maria-Hilf MRI Scanner 1', 'resource_type' => 'MRI scanner', 'clinic' => 'Maria-Hilf Krankenhaus', 'is_active' => false, 'allow_outside_availability' => true],
            ['name' => 'Intertran Other', 'resource_type' => 'Other', 'clinic' => 'Intertran', 'is_active' => true, 'allow_outside_availability' => true],
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

            // Create resource
            Resource::create([
                'name'                       => $row['name'],
                'resource_type_id'           => $resourceType->id,
                'clinic_id'                  => $clinic->id,
                'is_active'                  => $row['is_active'],
                'allow_outside_availability' => $row['allow_outside_availability'],
            ]);

            $created++;
        }

        $this->command->info("ResourceSeeder completed: {$created} resources created, {$skipped} skipped.");
    }
}
