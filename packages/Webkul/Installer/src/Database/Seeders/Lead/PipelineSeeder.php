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

class PipelineSeeder extends BaseSeeder
{
    const STAGE_ORDER_LOST_PREFIX = 'order-lost';
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
                'is_won' => false,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren-start',
                'name' => 'Klant adviseren',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren-opvolgen',
                'name' => 'Klant adviseren opvolgen',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => 'won',
                'name' => trans('installer::app.seeders.lead.pipeline.pipeline-stages.won', [], $defaultLocale),
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
                'is_won' => true,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => 'lost',
                'name' => trans('installer::app.seeders.lead.pipeline.pipeline-stages.lost', [], $defaultLocale),
                'probability' => 0,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
                'is_won' => false,
                'is_lost' => true,
            ],
            // Hernia pipeline stages
            [
                'id' => ++$stageId,
                'code' => 'nieuwe-aanvraag-kwalificeren-hernia',
                'name' => 'Nieuwe aanvraag, kwalificeren',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren-start-hernia',
                'name' => 'Klant adviseren, geen MRI / contact / tel-en-tel',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren-will-mri-hernia',
                'name' => 'Klant adviseren, wenste of heeft MRI',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ],[
                'id' => ++$stageId,
                'code' => 'klant-adviseren-wachten-op-mri-hernia',
                'name' => 'Wachten op klant, MRI wordt opgestuurd',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => 'klant-adviseren-mri-binnen-hernia',
                'name' => 'Klant adviseren, MRI is binnen',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => 'won-hernia',
                'name' => trans('installer::app.seeders.lead.pipeline.pipeline-stages.won', [], $defaultLocale),
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
                'is_won' => true,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => 'lost-hernia',
                'name' => trans('installer::app.seeders.lead.pipeline.pipeline-stages.lost', [], $defaultLocale),
                'probability' => 0,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
                'is_won' => false,
                'is_lost' => true,
            ],
            // SALES Privatescan
            [
                'id' => ++$stageId,
                'code' => 'bestelling-voorbereiden',
                'name' => 'Geadviseerd, order bevestigen',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateScanSalesPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => 'order-verzonden',
                'name' => 'Order bevestigd, wachten op akkoord',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateScanSalesPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ],[
                'id' => ++$stageId,
                'code' => 'order-confirmed',
                'name' => 'Akkoord, kliniek bevestigen',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateScanSalesPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ],[
                'id' => ++$stageId,
                'code' => 'waiting-for-execution',
                'name' => 'Wachten op uitvoering',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateScanSalesPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ],
            [
                'id' => ++$stageId,
                'code' => 'waiting-reports',
                'name' => 'Uitgevoerd, wacht op rapporten',
                'description' => 'Status wordt door begeleidster kliniek gezet',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateScanSalesPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => 'reports-received',
                'name' => 'Rapporten ontvangen',
                'description' => 'Afhankelijk van wel of geen vertaling 1 of 2 acties-->rapport vertalen, rapport versturen',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateScanSalesPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ],[
                'id' => ++$stageId,
                'code' => 'order-won',
                'name' => 'Klantproces beëindigd',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateScanSalesPipelineId,
                'is_won' => true,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => self::STAGE_ORDER_LOST_PREFIX,
                'name' => 'Afvoeren',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $privateScanSalesPipelineId,
                'is_won' => false,
                'is_lost' => true,
            ],
            // workflow Hennia
            [
                'id' => ++$stageId,
                'code' => 'bestelling-voorbereiden-hernia',
                'name' => 'Bestelling voorbereiden',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaSalesPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ], [
                'id' => ++$stageId,
                'code' => 'order-verzenden-hernia',
                'name' => 'Order is verzonden',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaSalesPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ],[
                'id' => ++$stageId,
                'code' => self::STAGE_ORDER_LOST_PREFIX . '-hernia',
                'name' => 'Verloren',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaSalesPipelineId,
                'is_won' => false,
                'is_lost' => true,
            ],[
                'id' => ++$stageId,
                'code' => 'order-won-hernia',
                'name' => 'Gewonnen',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $herniaSalesPipelineId,
                'is_won' => true,
                'is_lost' => false,
            ],
            // workflow tech pipeline
            [
                'id' => ++$stageId,
                'code' => 'no-pipeline',
                'name' => 'no-pipeline',
                'probability' => 100,
                'sort_order' => $stageId,
                'lead_pipeline_id' => $techBacklogPipelineId,
                'is_won' => false,
                'is_lost' => false,
            ],
        ];

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
