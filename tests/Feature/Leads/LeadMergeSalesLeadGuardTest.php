<?php

use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Lead;

function createSalesLeadFor(int $leadId): void
{
    DB::table('salesleads')->insert([
        'name'              => 'Sales lead for '.$leadId,
        'pipeline_stage_id' => 1,
        'lead_id'           => $leadId,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
}

test('manual merge swaps primary when only the manually picked lead has a sales lead', function () {
    $this->seed(TestSeeder::class);

    Lead::unsetEventDispatcher();

    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutMiddleware(Authenticate::class);

    $current = Lead::factory()->create();
    $manual = Lead::factory()->create();

    createSalesLeadFor($manual->id);

    $response = $this->get(route('admin.leads.duplicates.index', ['id' => $current->id, 'with' => $manual->id]));

    // The lead with the sales lead must become primary instead of the one being viewed.
    $response->assertRedirect(route('admin.leads.duplicates.index', ['id' => $manual->id, 'with' => $current->id]));
});

test('manual merge does not swap and disables the row when both leads have a sales lead', function () {
    $this->seed(TestSeeder::class);

    Lead::unsetEventDispatcher();

    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutMiddleware(Authenticate::class);

    $current = Lead::factory()->create();
    $manual = Lead::factory()->create();

    createSalesLeadFor($current->id);
    createSalesLeadFor($manual->id);

    $response = $this->get(route('admin.leads.duplicates.index', ['id' => $current->id, 'with' => $manual->id]));

    $response->assertOk();

    $duplicatesData = $response->viewData('duplicatesData');
    $manualRow = collect($duplicatesData)->firstWhere('id', $manual->id);

    expect($manualRow['has_sales_lead'])->toBeTrue()
        ->and($response->viewData('preselectedLeadIds'))->not->toContain($manual->id);
});

test('manual merge preselects and does not swap when neither lead has a sales lead', function () {
    $this->seed(TestSeeder::class);

    Lead::unsetEventDispatcher();

    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutMiddleware(Authenticate::class);

    $current = Lead::factory()->create();
    $manual = Lead::factory()->create();

    $response = $this->get(route('admin.leads.duplicates.index', ['id' => $current->id, 'with' => $manual->id]));

    $response->assertOk();

    $duplicatesData = $response->viewData('duplicatesData');
    $manualRow = collect($duplicatesData)->firstWhere('id', $manual->id);

    expect($manualRow['has_sales_lead'])->toBeFalse()
        ->and($response->viewData('preselectedLeadIds'))->toContain($manual->id);
});
