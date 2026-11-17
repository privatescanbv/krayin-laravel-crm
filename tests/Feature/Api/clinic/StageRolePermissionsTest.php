<?php

use App\Enums\PipelineDefaultKeys;
use App\Enums\RoleCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\User\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID, PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID] as $pipeline) {
        DB::table('lead_pipelines')->insertOrIgnore(['id' => $pipeline->value, 'name' => 'Pipeline '.$pipeline->value, 'is_default' => 0, 'rotten_days' => 30]);
    }
});

it('migration grants all existing hernia stages to both portal roles and is idempotent', function () {
    $stage = Stage::factory()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value]);
    $other = Stage::factory()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value]);
    foreach (RoleCode::cases() as $code) {
        Role::factory()->create(['code' => $code->value]);
    }

    $migration = require database_path('migrations/2026_11_17_100000_grant_portal_roles_to_hernia_sales_stages.php');
    $migration->up();
    $migration->up();

    expect($stage->roles()->count())->toBe(2)
        ->and($other->roles()->count())->toBe(0);
});

it('stores the role checkboxes per stage, and no boxes means visible for nobody', function () {
    $stage = Stage::factory()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value]);
    $arts = Role::factory()->create(['code' => RoleCode::ARTS->value]);
    $stage->roles()->attach($arts->id);
    $pipeline = fn (array $stageData) => app(PipelineRepository::class)->update([
        'name'   => 'Hernia',
        'stages' => [$stage->id => ['name' => $stage->name, 'portal_roles_form' => 1] + $stageData],
    ], PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value);

    $pipeline(['role_ids' => []]);
    expect(Stage::visibleForRole($arts->id)->count())->toBe(0);

    $pipeline(['role_ids' => [$arts->id]]);
    expect(Stage::visibleForRole($arts->id)->count())->toBe(1);
});
