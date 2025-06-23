<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PipelineSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('lead_pipelines')->delete();

        DB::table('lead_pipeline_stages')->delete();

        $now = Carbon::now();

        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        $privateSanPipelineId = 1;
        $herniaPipelineId = 2;
        DB::table('lead_pipelines')->insert([
            [
                'id'         => $privateSanPipelineId,
                'name'       => 'Privatescan',
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'         => $herniaPipelineId,
                'name'       => 'Hernia',
                'is_default' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $stageId = 0;
        DB::table('lead_pipeline_stages')->insert($data = [
            [
                'id'               => ++$stageId,
                'code'             => 'nieuwe-aanvraag-kwalificeren',
                'name'             => 'Nieuwe aanvraag kwalificeren',
                'probability'      => 100,
                'sort_order'       => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
            ],[
                'id'               => ++$stageId,
                'code'             => 'klant-adviseren',
                'name'             => 'Klant adviseren',
                'probability'      => 100,
                'sort_order'       => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
            ], [
                'id'               => ++$stageId,
                'code'             => 'klant-adviseren-opvolgen',
                'name'             => 'Klant adviseren opvolgen',
                'probability'      => 100,
                'sort_order'       => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
            ], [
                'id'               => ++$stageId,
                'code'             => 'won',
                'name'             => trans('installer::app.seeders.lead.pipeline.pipeline-stages.won', [], $defaultLocale),
                'probability'      => 100,
                'sort_order'       => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
            ], [
                'id'               => ++$stageId,
                'code'             => 'lost',
                'name'             => trans('installer::app.seeders.lead.pipeline.pipeline-stages.lost', [], $defaultLocale),
                'probability'      => 0,
                'sort_order'       => $stageId,
                'lead_pipeline_id' => $privateSanPipelineId,
            ],
      // Hernia pipeline stages
            [
                'id'               => ++$stageId,
                'code'             => 'nieuwe-aanvraag-kwalificeren-hernia',
                'name'             => 'Nieuwe aanvraag kwalificeren',
                'probability'      => 100,
                'sort_order'       => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ],[
                'id'               => ++$stageId,
                'code'             => 'klant-adviseren-hernia',
                'name'             => 'Klant adviseren',
                'probability'      => 100,
                'sort_order'       => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ], [
                'id'               => ++$stageId,
                'code'             => 'klant-adviseren-opvolgen-hernia',
                'name'             => 'Klant adviseren opvolgen',
                'probability'      => 100,
                'sort_order'       => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ], [
                'id'               => ++$stageId,
                'code'             => 'won-hernia',
                'name'             => trans('installer::app.seeders.lead.pipeline.pipeline-stages.won', [], $defaultLocale),
                'probability'      => 100,
                'sort_order'       => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ], [
                'id'               => ++$stageId,
                'code'             => 'lost-hernia',
                'name'             => trans('installer::app.seeders.lead.pipeline.pipeline-stages.lost', [], $defaultLocale),
                'probability'      => 0,
                'sort_order'       => $stageId,
                'lead_pipeline_id' => $herniaPipelineId,
            ],
        ]);
    }
}
