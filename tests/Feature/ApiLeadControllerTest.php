<?php

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Http\Controllers\Api\LeadController;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->leadController = app(LeadController::class);

    // Clear existing data first to ensure clean state
    Pipeline::query()->delete();

    // Create the exact seeder data for this test
    Pipeline::create([
        'id'         => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
        'name'       => 'Privatescan',
        'is_default' => 1,
        'type'       => PipelineType::LEAD,
    ]);

    Pipeline::create([
        'id'         => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,
        'name'       => 'Hernia',
        'is_default' => 0,
        'type'       => PipelineType::LEAD,
    ]);
    Lead::query()->delete();
});

test('getDefaultPipelineByType returns default workflow pipeline when exists', function () {

    $pipelineId = Pipeline::where('name', '=', 'Privatescan')->firstOrFail()->id;
    $this->assertNotNull($pipelineId);
    $stageId = 0;
    DB::table('lead_pipeline_stages')->insert($data = [
        [
            'id'               => ++$stageId,
            'code'             => 'stage1',
            'name'             => 'stage1',
            'probability'      => 100,
            'sort_order'       => $stageId,
            'lead_pipeline_id' => $pipelineId,
        ],
        [
            'id'               => ++$stageId,
            'code'             => 'stage2',
            'name'             => 'stage2',
            'probability'      => 100,
            'sort_order'       => $stageId,
            'lead_pipeline_id' => $pipelineId,
        ],
    ]);

    $lead = Lead::factory()->create([
        'title'                  => 'Test Lead',
        'lead_pipeline_id'       => $pipelineId,
        'lead_pipeline_stage_id' => 1,
    ]);
    $this->leadController->nextStage($lead->id);

    $lead = Lead::find($lead->id)->refresh();
    expect($lead->lead_pipeline_stage_id)->toBe(2);
});
