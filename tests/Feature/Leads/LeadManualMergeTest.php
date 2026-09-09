<?php

use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Carbon;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    Lead::unsetEventDispatcher();

    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutMiddleware(Authenticate::class);
    $this->withoutVite();
});

test('view lead page shows manual merge action', function () {
    $lead = Lead::factory()->create();

    $response = $this->get(route('admin.leads.view', $lead->id));

    $response->assertOk()
        ->assertSee('Lead samenvoegen')
        ->assertSee(route('admin.leads.duplicates.select', $lead->id), false);
});

test('manual merge select page is available from a lead', function () {
    $lead = Lead::factory()->create([
        'first_name' => 'Primaire',
        'last_name'  => 'Lead',
    ]);

    $response = $this->get(route('admin.leads.duplicates.select', $lead->id));

    $response->assertOk()
        ->assertSee('Lead samenvoegen')
        ->assertSee('Andere lead zoeken')
        ->assertSee('Verder')
        ->assertSee(route('admin.leads.search'), false);
});

test('merge screen accepts a manually selected lead outside the automatic detection window', function () {
    $primary = Lead::factory()->create([
        'first_name' => 'Jan',
        'last_name'  => 'Jansen',
        'created_at' => Carbon::now(),
    ]);

    $oldDuplicate = Lead::factory()->create([
        'first_name' => 'Andere',
        'last_name'  => 'Persoon',
        'created_at' => Carbon::now()->subWeeks(LeadRepository::DUPLICATE_SEARCH_PERIOD_WEEKS + 2),
    ]);

    // Automatic detection should not include the old lead
    expect(app(LeadRepository::class)->findPotentialDuplicates($primary)->pluck('id'))
        ->not->toContain($oldDuplicate->id);

    $response = $this->get(route('admin.leads.duplicates.index', [
        'id'   => $primary->id,
        'with' => $oldDuplicate->id,
    ]));

    $response->assertOk();

    $duplicatesData = $response->viewData('duplicatesData');
    $preselectedLeadIds = $response->viewData('preselectedLeadIds');

    expect(collect($duplicatesData)->pluck('id'))->toContain($oldDuplicate->id)
        ->and($preselectedLeadIds)->toContain($oldDuplicate->id)
        ->and($response->viewData('duplicates')->count())->toBeGreaterThan(0);
});

test('merge screen ignores with equal to the primary lead', function () {
    $lead = Lead::factory()->create();

    $response = $this->get(route('admin.leads.duplicates.index', [
        'id'   => $lead->id,
        'with' => $lead->id,
    ]));

    $response->assertOk();

    expect($response->viewData('preselectedLeadIds'))->toBe([])
        ->and($response->viewData('duplicates')->pluck('id'))->not->toContain($lead->id);
});

test('manual merge still uses the existing merge endpoint', function () {
    $primary = Lead::factory()->create([
        'first_name' => 'Keep',
        'last_name'  => 'Primary',
    ]);

    $duplicate = Lead::factory()->create([
        'first_name' => 'Merge',
        'last_name'  => 'Away',
        'created_at' => Carbon::now()->subWeeks(LeadRepository::DUPLICATE_SEARCH_PERIOD_WEEKS + 3),
    ]);

    $response = $this->postJson(route('admin.leads.duplicates.merge', $primary->id), [
        'primary_lead_id'     => $primary->id,
        'duplicate_lead_ids'  => [$duplicate->id],
        'field_mappings'      => [],
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect(Lead::find($duplicate->id))->toBeNull()
        ->and(Lead::withTrashed()->find($duplicate->id))->not->toBeNull()
        ->and(Lead::find($primary->id))->not->toBeNull();
});
