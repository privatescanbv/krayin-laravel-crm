<?php

namespace Tests\Feature;

use App\Enums\PipelineType;
use App\Models\Anamnesis;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Http;
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
    $person = Person::factory()->create();
    $lead->attachPersons([$person->id]);

    $salesLead = SalesLead::create([
        'name'              => 'Update Me',
        'pipeline_stage_id' => test()->stage->id,
        'lead_id'           => $lead->id,
    ]);

    $payload = [
        'name'        => 'Updated sales',
        'description' => 'Now updated',
        'person_ids'  => [$person->id],
        '_method'     => 'put',
    ];

    $response = $this->withHeaders(['HTTP_X-Requested-With' => 'XMLHttpRequest'])
        ->postJson(route('admin.sales-leads.update', ['id' => $salesLead->id]), $payload);

    $response->assertOk()->assertJsonPath('message', __('messages.sales.updated'));

    $this->assertDatabaseHas('salesleads', [
        'id'   => $salesLead->id,
        'name' => 'Updated sales',
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

test('can create Sales with person relationships', function () {
    $lead = Lead::factory()->create();
    $person1 = Person::factory()->create();
    $person2 = Person::factory()->create();

    $payload = [
        'name'              => 'Sales with Persons',
        'description'       => 'Created with person relationships',
        'pipeline_stage_id' => test()->stage->id,
        'lead_id'           => $lead->id,
        'person_ids'        => [$person1->id, $person2->id],
    ];

    $response = $this->postJson(route('admin.sales-leads.store'), $payload);
    $response->assertStatus(302);

    $salesLead = SalesLead::where('name', 'Sales with Persons')->first();
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

test('can copy persons from lead when creating Sales', function () {
    $lead = Lead::factory()->create();
    $person1 = Person::factory()->create();
    $person2 = Person::factory()->create();

    // Attach persons to the lead
    $lead->attachPersons([$person1->id, $person2->id]);

    $payload = [
        'name'              => 'Sales with Copied Persons',
        'description'       => 'Created with persons copied from lead',
        'pipeline_stage_id' => test()->stage->id,
        'lead_id'           => $lead->id,
    ];

    $response = $this->postJson(route('admin.sales-leads.store'), $payload);
    $response->assertStatus(302);

    $salesLead = SalesLead::where('name', 'Sales with Copied Persons')->first();
    expect($salesLead)->not->toBeNull();
    expect($salesLead->persons()->count())->toBe(2);
    expect($salesLead->persons->pluck('id')->toArray())->toContain($person1->id, $person2->id);
});

test('can update Sales person relationships', function () {
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
        'name'        => 'Updated Sales',
        'description' => 'Updated with new persons',
        'person_ids'  => [$person2->id, $person3->id],
        '_method'     => 'put',
    ];

    $response = $this->withHeaders(['HTTP_X-Requested-With' => 'XMLHttpRequest'])
        ->postJson(route('admin.sales-leads.update', ['id' => $salesLead->id]), $payload);

    $response->assertOk()->assertJsonPath('message', __('messages.sales.updated'));

    // Refresh the Sales
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

test('cannot remove all person relationships from Sales', function () {
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
        'name'        => 'Updated Sales',
        'description' => 'Try to remove all persons',
        'person_ids'  => [],
        '_method'     => 'put',
    ];

    $response = $this->withHeaders(['HTTP_X-Requested-With' => 'XMLHttpRequest'])
        ->postJson(route('admin.sales-leads.update', ['id' => $salesLead->id]), $payload);

    // Should fail validation - at least one person is required
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['person_ids']);

    // Refresh the Sales - persons should still be attached
    $salesLead->refresh();
    expect($salesLead->persons()->count())->toBe(2);

    // Check database relationships are still present
    $this->assertDatabaseHas('saleslead_persons', [
        'saleslead_id' => $salesLead->id,
        'person_id'    => $person1->id,
    ]);
    $this->assertDatabaseHas('saleslead_persons', [
        'saleslead_id' => $salesLead->id,
        'person_id'    => $person2->id,
    ]);
});

test('can detach gvl form from anamnesis when forms api returns 200', function () {
    config([
        'services.portal.patient.api_url'   => 'http://forms',
        'services.portal.patient.api_token' => 'test-token',
    ]);

    $lead = Lead::factory()->create();
    $person = Person::factory()->create();
    $lead->persons()->attach($person->id);

    $originalLink = 'https://forms.example.com/forms/123';
    $anamnesis = Anamnesis::factory()->create([
        'lead_id'       => $lead->id,
        'person_id'     => $person->id,
        'gvl_form_link' => $originalLink,
    ]);

    Http::fake([
        'http://forms/api/forms/123' => Http::response([], 200),
    ]);

    $response = $this->deleteJson(route('admin.anamnesis.gvl-form.detach', ['id' => $anamnesis->id]));

    $response->assertOk()
        ->assertJsonPath('message', 'GVL formulier is ontkoppeld.');

    $this->assertDatabaseHas('anamnesis', [
        'id'            => $anamnesis->id,
        'gvl_form_link' => null,
    ]);

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && str_contains($request->url(), 'http://forms/api/forms/123')
            && ($request->header('X-API-KEY')[0] ?? null) === 'test-token';
    });
});

test('gvl form stays linked to anamnesis when forms api responds with error', function () {
    config([
        'services.portal.patient.api_url'   => 'http://forms',
        'services.portal.patient.api_token' => 'test-token',
    ]);

    $lead = Lead::factory()->create();
    $person = Person::factory()->create();
    $lead->persons()->attach($person->id);

    $originalLink = 'https://forms.example.com/forms/456';
    $anamnesis = Anamnesis::factory()->create([
        'lead_id'       => $lead->id,
        'person_id'     => $person->id,
        'gvl_form_link' => $originalLink,
    ]);

    Http::fake([
        'http://forms/api/forms/456' => Http::response(['message' => 'not found'], 404),
    ]);

    $response = $this->deleteJson(route('admin.anamnesis.gvl-form.detach', ['id' => $anamnesis->id]));

    $response->assertStatus(404)
        ->assertJsonPath('message', 'not found');

    $this->assertDatabaseHas('anamnesis', [
        'id'            => $anamnesis->id,
        'gvl_form_link' => $originalLink,
    ]);

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && str_contains($request->url(), 'http://forms/api/forms/456')
            && ($request->header('X-API-KEY')[0] ?? null) === 'test-token';
    });
});
