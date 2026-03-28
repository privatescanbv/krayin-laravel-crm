<?php

namespace Database\Seeders;

use App\Enums\PipelineStage;
use App\Enums\PipelineType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds lead_pipelines and lead_pipeline_stages with fixed IDs matching {@see PipelineStage} META.
 * Replaces the removed Webkul Installer PipelineSeeder for tests and local setups.
 */
class TestPipelineSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $pipelines = [
            [
                'id'           => 1,
                'name'         => 'Privatescan Lead',
                'is_default'   => 1,
                'type'         => PipelineType::LEAD->value,
                'rotten_days'  => 30,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'id'           => 2,
                'name'         => 'Hernia Lead',
                'is_default'   => 0,
                'type'         => PipelineType::LEAD->value,
                'rotten_days'  => 30,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'id'           => 3,
                'name'         => 'Privatescan Sales',
                'is_default'   => 1,
                'type'         => PipelineType::BACKOFFICE->value,
                'rotten_days'  => 30,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'id'           => 4,
                'name'         => 'Hernia Sales',
                'is_default'   => 0,
                'type'         => PipelineType::BACKOFFICE->value,
                'rotten_days'  => 30,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'id'           => 5,
                'name'         => 'Technical',
                'is_default'   => 0,
                'type'         => PipelineType::BACKOFFICE->value,
                'rotten_days'  => 30,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'id'           => 6,
                'name'         => 'Privatescan Orders',
                'is_default'   => 1,
                'type'         => PipelineType::ORDER->value,
                'rotten_days'  => 30,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'id'           => 7,
                'name'         => 'Hernia Orders',
                'is_default'   => 0,
                'type'         => PipelineType::ORDER->value,
                'rotten_days'  => 30,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
        ];

        DB::table('lead_pipelines')->insert($pipelines);

        $stages = [];
        foreach (PipelineStage::cases() as $stage) {
            $stages[] = $stage->toArray();
        }

        DB::table('lead_pipeline_stages')->insert($stages);
    }
}
