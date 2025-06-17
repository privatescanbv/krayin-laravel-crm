<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use App\Enums\LeadPipelineStage;
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

        DB::table('lead_pipelines')->insert([
            [
                'id'         => 1,
                'name'       => trans('installer::app.seeders.lead.pipeline.default', [], $defaultLocale),
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $stages = collect(LeadPipelineStage::cases())->map(function ($stage, $index) use ($now) {
            return [
                'id'               => $index + 1,
                'code'             => $stage->value,
                'name'             => $stage->label(),
                'probability'      => $stage === LeadPipelineStage::LOST ? 0 : 100,
                'sort_order'       => $index + 1,
                'lead_pipeline_id' => 1
            ];
        })->toArray();

        DB::table('lead_pipeline_stages')->insert($stages);
    }
}
