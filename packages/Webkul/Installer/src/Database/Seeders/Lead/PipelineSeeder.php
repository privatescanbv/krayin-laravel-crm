<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use App\Enums\Departments;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Enums\PipelineType;
use Carbon\Carbon;
use Database\Seeders\BaseSeeder;
use Exception;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Stage;
use App\Services\LeadStatusTransitionValidator;

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
        $privateScanWorkflowPipelineId = 3;
        $herniaWorkflowPipelineId = 4;

        // should always be empty, used for new leads
        $techBacklogPipelineId = PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value;;

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
                'id' => $privateScanWorkflowPipelineId,
                'name' => 'Privatescan',
                'is_default' => 1,
                'type' => PipelineType::BACKOFFICE,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $herniaWorkflowPipelineId,
                'name' => 'Herniapoli',
                'is_default' => 0,
                'type' => PipelineType::BACKOFFICE,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $techBacklogPipelineId,
                'name' => 'No Pipeline',
                'is_default' => 0,
                'type' => PipelineType::LEAD,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $stageId = 0;
        $stages = [
            [
                'id' => ++$stageId,
                'code' => 'nieuwe-aanvraag-kwalificeren',
                'name' => 'Nieuwe aanvraag, kwalificeren',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren-start',
                'name' => 'Klant adviseren',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren-opvolgen',
                'name' => 'Klant adviseren opvolgen',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'won',
                'name' => trans('installer::app.seeders.lead.pipeline.pipeline-stages.won', [], $defaultLocale),
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'lost',
                'name' => trans('installer::app.seeders.lead.pipeline.pipeline-stages.lost', [], $defaultLocale),
                'probability' => 0,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
            ],
            // Hernia pipeline stages
            [
                'id' => ++$stageId,
                'code' => 'nieuwe-aanvraag-kwalificeren-hernia',
                'name' => 'Nieuwe aanvraag, kwalificeren',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren-start-hernia',
                'name' => 'Klant adviseren, geen MRI / contact / tel-en-tel',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren-will-mri-hernia',
                'name' => 'Klant adviseren, wenste of heeft MRI',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ],[
                'id' => ++$stageId,
                'code' => 'klant-adviseren-wachten-op-mri-hernia',
                'name' => 'Wachten op klant, MRI wordt opgestuurd',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren-mri-binnen-hernia',
                'name' => 'Klant adviseren, MRI is binnen',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'won-hernia',
                'name' => trans('installer::app.seeders.lead.pipeline.pipeline-stages.won', [], $defaultLocale),
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'lost-hernia',
                'name' => trans('installer::app.seeders.lead.pipeline.pipeline-stages.lost', [], $defaultLocale),
                'probability' => 0,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ],
            // workflow Privatescan
            [
                'id' => ++$stageId,
                'code' => 'bestelling-voorbereiden',
                'name' => 'Bestelling voorbereiden',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateScanWorkflowPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'order-verzonden',
                'name' => 'Order verzonden',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateScanWorkflowPipelineId,
            ],
            // workflow Hennia
            [
                'id' => ++$stageId,
                'code' => 'bestelling-voorbereiden-hernia',
                'name' => 'Bestelling voorbereiden',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaWorkflowPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'order-verzenden-hernia',
                'name' => 'Order is verzonden',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaWorkflowPipelineId,
            ],
            // workflow tech pipline
            [
                'id' => ++$stageId,
                'code' => 'no-pipeline',
                'name' => 'no-pipeline',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $techBacklogPipelineId,
            ],
        ];

        //check
        if($stageId != PipelineDefaultKeys::PIPELINE_TECHNICAL_STAGE_ID->value)
        {
            throw new Exception('Pipeline stage id is not valid: ' . $stageId);
        }

        DB::table('lead_pipeline_stages')->insert($stages);

        // validate
        $firstStageHerniaLeadPipeline = Stage::where('code', 'nieuwe-aanvraag-kwalificeren-hernia')->firstOrFail()->id;
        if (PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_HERNIA_ID->value != $firstStageHerniaLeadPipeline) {
            throw new Exception('Pipeline stage is niet geldig voor hernia: ' . $firstStageHerniaLeadPipeline);
        }
    }
}
