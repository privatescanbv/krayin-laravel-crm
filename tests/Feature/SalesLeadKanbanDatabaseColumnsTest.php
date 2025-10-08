<?php

use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Authenticate a back-office user on the correct guard
    $this->user = User::factory()->create(['name' => 'Admin Tester']);
    $this->actingAs($this->user, 'user');
    $this->withoutMiddleware(Authenticate::class);

    // Ensure workflow pipeline and stage exist (use the first available)
    $this->pipeline = Pipeline::first();
    $this->stage = Stage::first();
    if (! $this->pipeline || ! $this->stage) {
        throw new Exception('Pipeline or Stage not found. Ensure TestSeeder provisions them.');
    }
});

function getSalesLeadKanban(string $pipelineId)
{
    return test()->getJson(route('admin.workflow-leads.get', [
        'pipeline_id' => $pipelineId,
    ]));
}

test('saleslead kanban board loads without server errors', function () {
    // Create a base Lead linked to our SalesLead records
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Create a couple of SalesLeads in the same stage
    $wl1 = SalesLead::create([
        'name'              => 'Workflow One',
        'description'       => 'Test 1',
        'pipeline_stage_id' => $this->stage->id,
        'lead_id'           => $lead->id,
        'user_id'           => $this->user->id,
    ]);
    $wl2 = SalesLead::create([
        'name'              => 'Workflow Two',
        'description'       => 'Test 2',
        'pipeline_stage_id' => $this->stage->id,
        'lead_id'           => $lead->id,
        'user_id'           => $this->user->id,
    ]);

    $response = getSalesLeadKanban((string) $this->pipeline->id);
    $response->assertOk();

    // Check shape similar to leads kanban (reduced to essentials we return)
    $response->assertJsonStructure([
        '*' => [
            'id',
            'name',
            'sort_order',
            'leads' => [
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'pipeline_stage_id',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'total',
                    'current_page',
                    'per_page',
                    'last_page',
                ],
            ],
        ],
    ]);

    // Verify our SalesLeads are present in any stage bucket
    $all = collect($response->json())->flatMap(fn ($stage) => $stage['leads']['data']);
    $ids = $all->pluck('id');
    expect($ids)->toContain($wl1->id)->and($ids)->toContain($wl2->id);
});

test('saleslead kanban shows records per stage including pipeline filter', function () {
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Create second stage under same pipeline
    $secondStage = Stage::create([
        'lead_pipeline_id' => $this->pipeline->id,
        'name'             => 'WL Second',
        'code'             => 'wl_second',
        'sort_order'       => 2,
    ]);

    $wlStage1 = SalesLead::create([
        'name'              => 'WL Stage 1',
        'description'       => 'S1',
        'pipeline_stage_id' => $this->stage->id,
        'lead_id'           => $lead->id,
        'user_id'           => $this->user->id,
    ]);

    $wlStage2 = SalesLead::create([
        'name'              => 'WL Stage 2',
        'description'       => 'S2',
        'pipeline_stage_id' => $secondStage->id,
        'lead_id'           => $lead->id,
        'user_id'           => $this->user->id,
    ]);

    $response = getSalesLeadKanban((string) $this->pipeline->id);
    $response->assertOk();

    $json = $response->json();
    $stageIds = collect($json)->pluck('id');
    expect($stageIds)->toContain($this->stage->id)->and($stageIds)->toContain($secondStage->id);

    $wlIds = collect($json)->flatMap(fn ($s) => $s['leads']['data'])->pluck('id');
    expect($wlIds)->toContain($wlStage1->id)->and($wlIds)->toContain($wlStage2->id);
});
