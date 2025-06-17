<?php

use App\Enums\LeadPipelineStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // remove lead pipeline stages with pipeline id 1
        DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', 1)
            ->delete();

        // add all values of LeadPipelineStage with pipeline id 1
        $stages = collect(LeadPipelineStage::cases())->map(function ($stage) {
            return [
                'name'             => $stage->label(),
                'code'             => $stage->value,
                'lead_pipeline_id' => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        })->toArray();

        DB::table('lead_pipeline_stages')->insert($stages);
    }

    public function down()
    {
        // remove all stages with pipeline id 1
        DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', 1)
            ->delete();
    }
};
