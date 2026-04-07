<?php

namespace Database\Seeders;

use App\Enums\Departments;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Enums\PipelineType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds lead_pipelines and lead_pipeline_stages with fixed IDs matching {@see PipelineStage} META.
 * Replaces the removed Webkul Installer PipelineSeeder for tests and local setups.
 */
class PipelineSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $ps = Departments::PRIVATESCAN->value;
        $hp = Departments::HERNIA->value;

        DB::table('lead_pipelines')->insert([
            ['id' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,        'name' => $ps, 'is_default' => 1, 'type' => PipelineType::LEAD->value,      'rotten_days' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['id' => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,             'name' => $hp, 'is_default' => 0, 'type' => PipelineType::LEAD->value,      'rotten_days' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['id' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,  'name' => $ps, 'is_default' => 1, 'type' => PipelineType::BACKOFFICE->value, 'rotten_days' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,       'name' => $hp, 'is_default' => 0, 'type' => PipelineType::BACKOFFICE->value, 'rotten_days' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['id' => PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value,          'name' => '-', 'is_default' => 0, 'type' => PipelineType::LEAD->value,      'rotten_days' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['id' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value, 'name' => $ps, 'is_default' => 1, 'type' => PipelineType::ORDER->value,     'rotten_days' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['id' => PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value,      'name' => $hp, 'is_default' => 0, 'type' => PipelineType::ORDER->value,     'rotten_days' => 30, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('lead_pipeline_stages')->insert(
            array_map(fn (PipelineStage $s) => $s->toArray() + ['description' => null], PipelineStage::cases())
        );
    }
}
