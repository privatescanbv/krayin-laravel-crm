<?php

use App\Enums\ActivityType;
use Database\Seeders\TestSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Automation\Models\Workflow;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('lead workflow create_activity with user_id assigns that user to the created activity', function () {
    $user = User::factory()->create();

    $pipeline = Pipeline::factory()->create();
    $stageA = Stage::create([
        'name'             => 'Start',
        'lead_pipeline_id' => $pipeline->id,
        'code'             => 'start',
        'sort_order'       => 1,
        'is_won'           => false,
        'is_lost'          => false,
    ]);
    $stageB = Stage::create([
        'name'             => 'Target',
        'lead_pipeline_id' => $pipeline->id,
        'code'             => 'target',
        'sort_order'       => 2,
        'is_won'           => false,
        'is_lost'          => false,
    ]);

    Workflow::create([
        'name'           => 'Test lead user assignment',
        'description'    => 'Test',
        'entity_type'    => 'leads',
        'event'          => 'lead.update_stage.after',
        'condition_type' => 'and',
        'conditions'     => [
            [
                'value'          => (string) $stageB->id,
                'operator'       => '==',
                'attribute'      => 'lead_pipeline_stage_id',
                'attribute_type' => 'select',
            ],
        ],
        'actions' => [
            [
                'id'         => 'create_activity',
                'attributes' => [
                    'title'            => 'Assigned lead activity',
                    'type'             => ActivityType::TASK->value,
                    'deadline_in_days' => 1,
                    'user_id'          => $user->id,
                ],
            ],
        ],
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stageA->id,
    ]);

    $lead->update(['lead_pipeline_stage_id' => $stageB->id]);

    $activity = Activity::where('lead_id', $lead->id)
        ->where('type', '!=', ActivityType::SYSTEM->value)
        ->where('title', 'Assigned lead activity')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->user_id)->toBe($user->id);
});

test('lead workflow create_activity without user_id creates activity without specific user assignment', function () {
    $pipeline = Pipeline::factory()->create();
    $stageA = Stage::create([
        'name'             => 'Start',
        'lead_pipeline_id' => $pipeline->id,
        'code'             => 'start2',
        'sort_order'       => 1,
        'is_won'           => false,
        'is_lost'          => false,
    ]);
    $stageB = Stage::create([
        'name'             => 'Target',
        'lead_pipeline_id' => $pipeline->id,
        'code'             => 'target2',
        'sort_order'       => 2,
        'is_won'           => false,
        'is_lost'          => false,
    ]);

    Workflow::create([
        'name'           => 'Test lead no user',
        'description'    => 'Test',
        'entity_type'    => 'leads',
        'event'          => 'lead.update_stage.after',
        'condition_type' => 'and',
        'conditions'     => [
            [
                'value'          => (string) $stageB->id,
                'operator'       => '==',
                'attribute'      => 'lead_pipeline_stage_id',
                'attribute_type' => 'select',
            ],
        ],
        'actions' => [
            [
                'id'         => 'create_activity',
                'attributes' => [
                    'title'   => 'Unassigned lead activity',
                    'type'    => ActivityType::TASK->value,
                    'user_id' => '',
                ],
            ],
        ],
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stageA->id,
    ]);

    $lead->update(['lead_pipeline_stage_id' => $stageB->id]);

    $activity = Activity::where('lead_id', $lead->id)
        ->where('title', 'Unassigned lead activity')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->user_id)->toBeNull();
});
