<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use App\Enums\Departments;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Enums\PipelineStageDefaultKeys;
use App\Enums\PipelineType;
use Carbon\Carbon;
use Database\Seeders\BaseSeeder;
use Exception;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Stage;

class PipelineSeeder extends BaseSeeder
{
    /**
     * Seed the application's database.
     *
     * @param array $parameters
     * @return void
     * @throws Exception
     */
    public function run($parameters = [])
    {
        $this->truncateTables(['lead_pipeline_stages', 'lead_pipelines']);

        $now = Carbon::now();
        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        $privateSanPipelineId = PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value;
        $herniaPipelineId = PipelineDefaultKeys::PIPELINE_HERNIA_ID->value;
        $privateScanSalesPipelineId = PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value;
        $herniaSalesPipelineId = PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value;
        $techBacklogPipelineId = PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value;
        $privateScanOrdersPipelineId = PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value;
        $herniaOrdersPipelineId = PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value;

        // Insert pipelines with explicit IDs
        DB::table('lead_pipelines')->insert([
            [
                'id' => $privateSanPipelineId,
                'name' => Departments::PRIVATESCAN->value,
                'is_default' => 1,
                'type' => PipelineType::LEAD,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $herniaPipelineId,
                'name' => Departments::HERNIA->value,
                'is_default' => 0,
                'type' => PipelineType::LEAD,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $privateScanSalesPipelineId,
                'name' => 'Privatescan',
                'is_default' => 1,
                'type' => PipelineType::BACKOFFICE,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $herniaSalesPipelineId,
                'name' => 'Herniapoli',
                'is_default' => 0,
                'type' => PipelineType::BACKOFFICE,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $techBacklogPipelineId,
                'name' => '-',
                'is_default' => 0,
                'type' => PipelineType::LEAD,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $privateScanOrdersPipelineId,
                'name' => 'Privatescan Orders',
                'is_default' => 1,
                'type' => PipelineType::ORDER,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $herniaOrdersPipelineId,
                'name' => 'Herniapoli Orders',
                'is_default' => 0,
                'type' => PipelineType::ORDER,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $stageId = 0;
        $stages = [];
        foreach (PipelineStage::cases() as $stageEnum) {
            $stages[] = $stageEnum->toArray(++$stageId);
        }

        // Add description => null to all stages that don't have it
        foreach ($stages as &$stage) {
            if (!isset($stage['description'])) {
                $stage['description'] = null;
            }
        }
        unset($stage); // Break reference

        //check
        if($stageId != PipelineDefaultKeys::PIPELINE_TECHNICAL_STAGE_ID->value)
        {
            throw new Exception('Pipeline stage id is not valid: ' . $stageId . ' (expected ' . PipelineDefaultKeys::PIPELINE_TECHNICAL_STAGE_ID->value . ')');
        }

        DB::table('lead_pipeline_stages')->insert($stages);

        // validate
        $firstStageHerniaLeadPipeline = Stage::where('code', 'nieuwe-aanvraag-kwalificeren-hernia')->firstOrFail()->id;
        if (PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_HERNIA_ID->value != $firstStageHerniaLeadPipeline) {
            throw new Exception('Pipeline stage is niet geldig voor hernia: ' . $firstStageHerniaLeadPipeline);
        }
    }
}
