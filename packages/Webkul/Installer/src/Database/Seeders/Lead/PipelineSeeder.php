<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use App\Enums\LeadPipelineStageDefaults;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Enums\PipelineType;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\Pipeline;

class PipelineSeeder extends Seeder
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
//        // Check if the first pipeline already exists with correct name
//        $existingPipeline = Pipeline::find(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value);
//        if ($existingPipeline && $existingPipeline->name === 'Privatescan') {
//            // Also check if the first stage exists
//            $existingStage = Stage::find(1);
//            if ($existingStage && $existingStage->code === 'nieuwe-aanvraag-kwalificeren') {
//                return; // Pipeline and stages already exist, skip seeding
//            }
//        }
//    {

        if (Pipeline::query()->count() > 0) {
            // If pipelines already exist, skip seeding
            return;
        }
        // Use transaction to prevent race conditions during parallel testing
//            $existingPipeline = Pipeline::find(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value);
//            if ($existingPipeline) {
//                return;
//            }
        if (Pipeline::query()->count() > 0 || DB::table('lead_pipeline_stages')->count() > 0) {
            // If pipelines already exist, skip seeding
            return;
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('lead_pipeline_stages')->truncate();
        DB::table('lead_pipelines')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
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
                'name' => 'Privatescan',
                'is_default' => 1,
                'type' => PipelineType::LEAD,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $herniaPipelineId,
                'name' => 'Hernia',
                'is_default' => 0,
                'type' => PipelineType::LEAD,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $privateScanWorkflowPipelineId,
                'name' => 'Privatescan',
                'is_default' => 1,
                'type' => PipelineType::WORKFLOW,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $herniaWorkflowPipelineId,
                'name' => 'Hernia',
                'is_default' => 0,
                'type' => PipelineType::WORKFLOW,
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
                'name' => 'Nieuwe aanvraag kwalificeren',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren',
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
                'name' => 'Nieuwe aanvraag kwalificeren',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren-hernia',
                'name' => 'Klant adviseren',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren-opvolgen-hernia',
                'name' => 'Klant adviseren opvolgen',
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
                'code' => 'doe-nog-iets',
                'name' => 'Doe nog iets',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateScanWorkflowPipelineId,
            ],
            // workflow Hennia
            [
                'id' => ++$stageId,
                'code' => 'bestelling-voorbereiden-hernia',
                'name' => 'Bestelling voorbereiden Hernia',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaWorkflowPipelineId,
            ], [
                'id' => ++$stageId,
                'code' => 'doe-nog-iets-hernia',
                'name' => 'Doe nog iets Hernia',
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
