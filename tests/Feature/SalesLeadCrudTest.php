<?php

namespace Tests\Feature;

use App\Enums\PipelineType;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
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

test('can create sales lead with person relationships', function () {
    $lead = Lead::factory()->create();
    $person1 = Person::factory()->create();
    $person2 = Person::factory()->create();

    $payload = [
        'name'              => 'Sales Lead with Persons',
        'description'       => 'Created with person relationships',
        'pipeline_stage_id' => test()->stage->id,
        'lead_id'           => $lead->id,
        'person_ids'        => [$person1->id, $person2->id],
    ];

    $response = $this->postJson(route('admin.sales-leads.store'), $payload);
    $response->assertStatus(302);

    $salesLead = SalesLead::where('name', 'Sales Lead with Persons')->first();
    expect($salesLead)->not->toBeNull();
    expect($salesLead->persons()->count())->toBe(2);
    expect($salesLead->persons->pluck('id')->toArray())->toContain($person1->id, $person2->id);

    // Check database relationships
    $this->assertDatabaseHas('saleslead_persons', [
        'saleslead_id' => $salesLead->id,
        'person_id'    => $person1->id,
    ]);
    $this->assertDatabaseHas('saleslead_persons', [
        'saleslead_id' => $salesLead->id,
        'person_id'    => $person2->id,
    ]);
});

test('can copy persons from lead when creating sales lead', function () {
    $lead = Lead::factory()->create();
    $person1 = Person::factory()->create();
    $person2 = Person::factory()->create();

    // Attach persons to the lead
    $lead->attachPersons([$person1->id, $person2->id]);

    $payload = [
        'name'              => 'Sales Lead with Copied Persons',
        'description'       => 'Created with persons copied from lead',
        'pipeline_stage_id' => test()->stage->id,
        'lead_id'           => $lead->id,
    ];

    $response = $this->postJson(route('admin.sales-leads.store'), $payload);
    $response->assertStatus(302);

    $salesLead = SalesLead::where('name', 'Sales Lead with Copied Persons')->first();
    expect($salesLead)->not->toBeNull();
    expect($salesLead->persons()->count())->toBe(2);
    expect($salesLead->persons->pluck('id')->toArray())->toContain($person1->id, $person2->id);
});

test('can update sales lead person relationships', function () {
    $lead = Lead::factory()->create();
    $person1 = Person::factory()->create();
    $person2 = Person::factory()->create();
    $person3 = Person::factory()->create();

    $salesLead = SalesLead::create([
        'name'              => 'Update Persons',
        'pipeline_stage_id' => test()->stage->id,
        'lead_id'           => $lead->id,
    ]);

    // Initially attach person1
    $salesLead->attachPersons([$person1->id]);

    $payload = [
        'name'        => 'Updated Sales Lead',
        'description' => 'Updated with new persons',
        'person_ids'  => [$person2->id, $person3->id],
        '_method'     => 'put',
    ];

    $response = $this->withHeaders(['HTTP_X-Requested-With' => 'XMLHttpRequest'])
        ->postJson(route('admin.sales-leads.update', ['id' => $salesLead->id]), $payload);

    $response->assertOk()->assertJsonPath('message', 'Sales lead updated successfully.');

    // Refresh the sales lead
    $salesLead->refresh();
    expect($salesLead->persons()->count())->toBe(2);
    expect($salesLead->persons->pluck('id')->toArray())->toContain($person2->id, $person3->id);
    expect($salesLead->persons->pluck('id')->toArray())->not->toContain($person1->id);

    // Check database relationships
    $this->assertDatabaseHas('saleslead_persons', [
        'saleslead_id' => $salesLead->id,
        'person_id'    => $person2->id,
    ]);
    $this->assertDatabaseHas('saleslead_persons', [
        'saleslead_id' => $salesLead->id,
        'person_id'    => $person3->id,
    ]);
    $this->assertDatabaseMissing('saleslead_persons', [
        'saleslead_id' => $salesLead->id,
        'person_id'    => $person1->id,
    ]);
});

test('can remove all person relationships from sales lead', function () {
    $lead = Lead::factory()->create();
    $person1 = Person::factory()->create();
    $person2 = Person::factory()->create();

    $salesLead = SalesLead::create([
        'name'              => 'Remove All Persons',
        'pipeline_stage_id' => test()->stage->id,
        'lead_id'           => $lead->id,
    ]);

    // Initially attach persons
    $salesLead->attachPersons([$person1->id, $person2->id]);

    $payload = [
        'name'        => 'Updated Sales Lead',
        'description' => 'Removed all persons',
        'person_ids'  => [],
        '_method'     => 'put',
    ];

    $response = $this->withHeaders(['HTTP_X-Requested-With' => 'XMLHttpRequest'])
        ->postJson(route('admin.sales-leads.update', ['id' => $salesLead->id]), $payload);

    $response->assertOk()->assertJsonPath('message', 'Sales lead updated successfully.');

    // Refresh the sales lead
    $salesLead->refresh();
    expect($salesLead->persons()->count())->toBe(0);

    // Check database relationships are removed
    $this->assertDatabaseMissing('saleslead_persons', [
        'saleslead_id' => $salesLead->id,
        'person_id'    => $person1->id,
    ]);
    $this->assertDatabaseMissing('saleslead_persons', [
        'saleslead_id' => $salesLead->id,
        'person_id'    => $person2->id,
    ]);
});
