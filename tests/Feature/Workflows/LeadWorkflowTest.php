<?php

use App\Enums\ActivityType;
use App\Enums\LostReason;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Log;
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

test('lead workflow create_activity skips a duplicate open activity without logging an error', function () {
    $pipeline = Pipeline::factory()->create();
    $stageA = Stage::create([
        'name'       => 'Start', 'lead_pipeline_id' => $pipeline->id, 'code' => 'start3',
        'sort_order' => 1, 'is_won' => false, 'is_lost' => false,
    ]);
    $stageB = Stage::create([
        'name'       => 'Target', 'lead_pipeline_id' => $pipeline->id, 'code' => 'target3',
        'sort_order' => 2, 'is_won' => false, 'is_lost' => false,
    ]);

    Workflow::create([
        'name'           => 'Test duplicate activity',
        'description'    => 'Test',
        'entity_type'    => 'leads',
        'event'          => 'lead.update_stage.after',
        'condition_type' => 'and',
        'conditions'     => [[
            'value'          => (string) $stageB->id,
            'operator'       => '==',
            'attribute'      => 'lead_pipeline_stage_id',
            'attribute_type' => 'select',
        ]],
        'actions' => [[
            'id'         => 'create_activity',
            'attributes' => [
                'title'   => 'Klant data bijwerken',
                'type'    => ActivityType::TASK->value,
                'user_id' => '',
            ],
        ]],
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stageA->id,
    ]);

    // An open activity with the same title already exists.
    Activity::create([
        'title'   => 'Klant data bijwerken',
        'type'    => ActivityType::TASK->value,
        'lead_id' => $lead->id,
        'is_done' => 0,
    ]);

    Log::spy();

    $lead->update(['lead_pipeline_stage_id' => $stageB->id]);

    // No second activity, and the benign duplicate is logged as a warning - not an error
    // (the `sentry` log channel would turn an error into a Bugsink issue).
    expect(Activity::where('lead_id', $lead->id)->where('title', 'Klant data bijwerken')->count())->toBe(1);

    Log::shouldNotHaveReceived('error', [Mockery::pattern('/duplicate/i')]);
    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => str_contains($message, 'duplicate'))
        ->once();
});

test('lead workflow create_activity is skipped when the lead is moved back from a won stage', function () {
    $pipeline = Pipeline::factory()->create();
    $wonStage = Stage::create([
        'name'       => 'Won', 'lead_pipeline_id' => $pipeline->id, 'code' => 'won4',
        'sort_order' => 1, 'is_won' => true, 'is_lost' => false,
    ]);
    $qualifyStage = Stage::create([
        'name'       => 'Qualify', 'lead_pipeline_id' => $pipeline->id, 'code' => 'qualify4',
        'sort_order' => 2, 'is_won' => false, 'is_lost' => false,
    ]);

    Workflow::create([
        'name'           => 'Test won lead reactivation',
        'description'    => 'Test',
        'entity_type'    => 'leads',
        'event'          => 'lead.update_stage.after',
        'condition_type' => 'and',
        'conditions'     => [[
            'value'          => (string) $qualifyStage->id,
            'operator'       => '==',
            'attribute'      => 'lead_pipeline_stage_id',
            'attribute_type' => 'select',
        ]],
        'actions' => [[
            'id'         => 'create_activity',
            'attributes' => [
                'title'   => 'Klant data bijwerken',
                'type'    => ActivityType::TASK->value,
                'user_id' => '',
            ],
        ]],
    ]);

    // A lead that was already won, e.g. because a new inbound lead got linked
    // to the same person and the old lead's stage got reset to "qualify".
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $wonStage->id,
    ]);

    $lead->update(['lead_pipeline_stage_id' => $qualifyStage->id]);

    expect(Activity::where('lead_id', $lead->id)->where('title', 'Klant data bijwerken')->count())->toBe(0);
});

test('lead workflow create_activity is skipped when the lead is moving into a won/lost stage', function () {
    $pipeline = Pipeline::factory()->create();
    $qualifyStage = Stage::create([
        'name'       => 'Qualify', 'lead_pipeline_id' => $pipeline->id, 'code' => 'qualify5',
        'sort_order' => 1, 'is_won' => false, 'is_lost' => false,
    ]);
    $lostStage = Stage::create([
        'name'       => 'Lost', 'lead_pipeline_id' => $pipeline->id, 'code' => 'lost5',
        'sort_order' => 2, 'is_won' => false, 'is_lost' => true,
    ]);

    Workflow::create([
        'name'           => 'Test lead closing into lost',
        'description'    => 'Test',
        'entity_type'    => 'leads',
        'event'          => 'lead.update_stage.after',
        'condition_type' => 'and',
        'conditions'     => [[
            'value'          => (string) $lostStage->id,
            'operator'       => '==',
            'attribute'      => 'lead_pipeline_stage_id',
            'attribute_type' => 'select',
        ]],
        'actions' => [[
            'id'         => 'create_activity',
            'attributes' => [
                'title'   => 'Should not be created',
                'type'    => ActivityType::TASK->value,
                'user_id' => '',
            ],
        ]],
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $qualifyStage->id,
        'lost_reason'            => LostReason::Informative->value,
    ]);

    $lead->update(['lead_pipeline_stage_id' => $lostStage->id]);

    expect(Activity::where('lead_id', $lead->id)->where('title', 'Should not be created')->count())->toBe(0);
});
