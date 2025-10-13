<?php

namespace Tests\Feature;

use App\Enums\PipelineType;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $this->seed(TestSeeder::class);

    $user = makeUser();
    $this->actingAs($user, 'user');

    // Ensure we have a BACKOFFICE (workflow) pipeline and at least one stage
    $pipeline = Pipeline::where('type', PipelineType::BACKOFFICE)->first() ?? Pipeline::create([
        'name'        => 'Default Workflow Pipeline',
        'type'        => PipelineType::BACKOFFICE,
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);

    $stage = Stage::where('lead_pipeline_id', $pipeline->id)->first();
    if (! $stage) {
        $stage = Stage::create([
            'name'             => 'New',
            'code'             => 'new',
            'lead_pipeline_id' => $pipeline->id,
            'sort_order'       => 1,
        ]);
    }

    test()->pipeline = $pipeline;
    test()->stage = $stage;
});

function getKanbanLeadIds($response): array
{
    $data = $response->json();
    $ids = [];
    foreach ($data as $column) {
        if (isset($column['leads']['data'])) {
            foreach ($column['leads']['data'] as $lead) {
                $ids[] = $lead['id'];
            }
        }
    }

    return $ids;
}

test('workflow leads get returns kanban json with created leads', function () {
    $lead1 = Lead::factory()->create();
    $lead2 = Lead::factory()->create();

    $l1 = SalesLead::create([
        'name'              => 'Backoffice A',
        'description'       => 'First',
        'pipeline_stage_id' => test()->stage->id,
        'lead_id'           => $lead1->id,
    ]);
    $l2 = SalesLead::create([
        'name'              => 'Backoffice B',
        'description'       => 'Second',
        'pipeline_stage_id' => test()->stage->id,
        'lead_id'           => $lead2->id,
    ]);

    $response = $this->getJson(route('admin.sales-leads.get').'?pipeline_id='.test()->pipeline->id);
    $response->assertOk();

    $ids = getKanbanLeadIds($response);
    expect($ids)->toContain($l1->id, $l2->id);
});

test('can create workflow lead', function () {
    $lead = Lead::factory()->create();

    $payload = [
        'name'              => 'Created Sales Lead',
        'description'       => 'Created via test',
        'pipeline_stage_id' => test()->stage->id,
        'lead_id'           => $lead->id,
    ];

    $response = $this->postJson(route('admin.sales-leads.store'), $payload);
    // Controller redirects on success; assert redirect and DB has row
    $response->assertStatus(302);

    $this->assertDatabaseHas('salesleads', [
        'name'              => 'Created Sales Lead',
        'pipeline_stage_id' => test()->stage->id,
    ]);
});

test('can update workflow lead (ajax json)', function () {
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::create([
        'name'              => 'Update Me',
        'pipeline_stage_id' => test()->stage->id,
        'lead_id'           => $lead->id,
    ]);

    $payload = [
        'name'        => 'Updated Sales Lead',
        'description' => 'Now updated',
        '_method'     => 'put',
    ];

    $response = $this->withHeaders(['HTTP_X-Requested-With' => 'XMLHttpRequest'])
        ->postJson(route('admin.sales-leads.update', ['id' => $salesLead->id]), $payload);

    $response->assertOk()->assertJsonPath('message', 'Sales lead updated successfully.');

    $this->assertDatabaseHas('salesleads', [
        'id'   => $salesLead->id,
        'name' => 'Updated Sales Lead',
    ]);
});

test('can delete workflow lead', function () {
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::create([
        'name'              => 'Delete Me',
        'pipeline_stage_id' => test()->stage->id,
        'lead_id'           => $lead->id,
    ]);

    $response = $this->deleteJson(route('admin.sales-leads.delete', ['id' => $salesLead->id]));
    // Controller redirects; just assert it didn't fail and row is gone
    $response->assertStatus(302);

    $this->assertDatabaseMissing('salesleads', [
        'id' => $salesLead->id,
    ]);
});
