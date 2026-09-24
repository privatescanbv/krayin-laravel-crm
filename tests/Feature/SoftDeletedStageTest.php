<?php

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Recreate the removed 'Na-behandeling' stage as it exists in production.
    DB::table('lead_pipeline_stages')->insert([
        'id'               => 24,
        'code'             => 'sales-opname-hernia',
        'name'             => 'Na-behandeling',
        'lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
        'sort_order'       => 24,
        'is_won'           => false,
        'is_lost'          => false,
        'is_default'       => false,
    ]);
});

test('migration moves sales leads to 1e Nazorg and soft deletes Na-behandeling', function () {
    $salesLead = SalesLead::factory()->create(['pipeline_stage_id' => 24]);

    $migration = require database_path('migrations/2026_09_24_000000_soft_delete_na_behandeling_stage.php');
    $migration->down();
    $migration->up();

    expect($salesLead->fresh()->pipeline_stage_id)->toBe(PipelineStage::SALES_AFTERCARE1_HERNIA->id())
        ->and(Stage::find(24))->toBeNull()
        ->and(Stage::withTrashed()->find(24)->trashed())->toBeTrue();
});

test('soft deleted stage is hidden from pipeline stages', function () {
    Stage::find(24)->delete();

    $stageIds = Pipeline::find(PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value)->stages->pluck('id');

    expect($stageIds)->not->toContain(24)
        ->and($stageIds)->toContain(PipelineStage::SALES_AFTERCARE1_HERNIA->id());
});

test('sales lead still resolves a soft deleted stage', function () {
    $salesLead = SalesLead::factory()->create(['pipeline_stage_id' => 24]);
    Stage::find(24)->delete();

    expect($salesLead->fresh()->stage?->name)->toBe('Na-behandeling');
});
