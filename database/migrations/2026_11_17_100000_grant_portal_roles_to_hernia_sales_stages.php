<?php

use App\Enums\PipelineDefaultKeys;
use App\Enums\RoleCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleIds = DB::table('roles')->whereIn('code', array_column(RoleCode::cases(), 'value'))->pluck('id');
        $stageIds = DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value)
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($stageIds as $stageId) {
            foreach ($roleIds as $roleId) {
                DB::table('lead_pipeline_stage_roles')->insertOrIgnore([
                    'lead_pipeline_stage_id' => $stageId,
                    'role_id'                => $roleId,
                ]);
            }
        }
    }

    public function down(): void {}
};
