<?php

use App\Enums\ActivityType;
use App\Enums\Departments;
use App\Enums\PipelineStage;
use App\Models\Department;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Database\Seeders\WorkflowSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\Type;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->seed(WorkflowSeeder::class);
});

test('creating a sales lead at a stage with a workflow automatically creates an activity', function () {
    $stage = PipelineStage::SALES_IN_BEHANDELING; // id=13, entity='sales'

    $salesLead = SalesLead::factory()->create([
        'pipeline_stage_id' => $stage->id(),
    ]);

    expect(Activity::where('sales_lead_id', $salesLead->id)->count())->toBe(1);
});

test('updating a sales lead stage to one with a workflow automatically creates an activity', function () {
    // Start at won stage — WorkflowSeeder skips won stages, so no activity is created on creation
    $salesLead = SalesLead::factory()->create([
        'pipeline_stage_id' => PipelineStage::SALES_MET_SUCCES_AFGEROND->id(), // id=14, is_won, no workflow
    ]);

    expect(Activity::where('sales_lead_id', $salesLead->id)->count())->toBe(0);

    $salesLead->update(['pipeline_stage_id' => PipelineStage::SALES_IN_BEHANDELING->id()]);

    expect(Activity::where('sales_lead_id', $salesLead->id)->count())->toBe(1);
});

test('setting a lead to won stage creates a sales lead with a workflow activity', function () {
    // TestSeeder already seeds pipeline 3 (PIPELINE_PRIVATESCAN_SALES_ID) with stage 13 (SALES_IN_BEHANDELING)
    // WorkflowSeeder already seeds a workflow for stage 13 that creates an activity

    // Create a custom lead pipeline with a won stage
    $pipeline = Pipeline::factory()->create();
    $stageNew = Stage::create([
        'name'             => 'New',
        'lead_pipeline_id' => $pipeline->id,
        'code'             => 'new',
        'sort_order'       => 1,
        'is_won'           => false,
        'is_lost'          => false,
    ]);
    $stageWon = Stage::create([
        'name'             => 'Won',
        'lead_pipeline_id' => $pipeline->id,
        'code'             => 'won',
        'sort_order'       => 100,
        'is_won'           => true,
        'is_lost'          => false,
    ]);

    // Use the department seeded by TestSeeder → getWorkflowPipelineStageId() will return stage 13
    $department = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $user = User::factory()->create();
    $source = Source::create(['name' => 'Website']);
    $type = Type::create(['name' => 'New Lead']);

    $person = Person::factory()->create([
        'emails' => [['value' => 'test@example.com', 'label' => 'work', 'is_default' => true]],
        'phones' => [['value' => '+31612345678', 'label' => 'mobile', 'is_default' => true]],
    ]);

    $lead = new Lead([
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stageNew->id,
        'status'                 => 1,
        'first_name'             => $person->first_name,
        'last_name'              => $person->last_name,
        'emails'                 => [['value' => 'test@example.com', 'label' => 'work', 'is_default' => true]],
        'phones'                 => [['value' => '+31612345678', 'label' => 'mobile', 'is_default' => true]],
        'description'            => 'Test lead',
        'user_id'                => $user->id,
        'lead_source_id'         => $source->id,
        'lead_type_id'           => $type->id,
        'department_id'          => $department->id,
    ]);
    $lead->save();
    $lead->attachPersons([$person->id]);

    // Act: transition lead to won — triggers createFromWonLead → SalesLead at stage 13 → workflow activity
    $lead->update(['lead_pipeline_stage_id' => $stageWon->id]);

    $salesLead = SalesLead::where('lead_id', $lead->id)->first();
    expect($salesLead)->not->toBeNull();

    // Workflow must have created a non-system activity on the SalesLead
    $workflowActivityCount = Activity::where('sales_lead_id', $salesLead->id)
        ->where('type', '!=', ActivityType::SYSTEM->value)
        ->count();

    expect($workflowActivityCount)->toBeGreaterThanOrEqual(1);
});
