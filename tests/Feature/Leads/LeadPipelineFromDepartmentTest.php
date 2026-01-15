<?php

use App\Enums\Departments;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Models\Department;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');
});

test('creating a lead respects department pipeline even if a mismatching stage id is submitted', function () {
    $privatescanDepartment = Department::query()
        ->where('name', Departments::PRIVATESCAN->value)
        ->firstOrFail();

    // Simulate a stale/hidden UI field sending a Hernia stage while department is Privatescan
    $herniaStageId = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_HERNIA_ID->value;

    $response = $this->post(route('admin.leads.store'), [
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'department_id'          => $privatescanDepartment->id,
        'lead_pipeline_stage_id' => $herniaStageId,
        'emails'                 => [
            ['value' => 'john.doe@example.test', 'label' => 'eigen'],
        ],
    ]);

    $response->assertStatus(302);

    $lead = Lead::query()->latest('id')->firstOrFail();

    expect($lead->department_id)->toBe($privatescanDepartment->id)
        ->and($lead->lead_pipeline_id)->toBe(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value)
        ->and($lead->lead_pipeline_stage_id)->not->toBe($herniaStageId);
});
