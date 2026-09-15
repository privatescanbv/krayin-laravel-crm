<?php

use App\Exceptions\CannotDeleteLeadWithSalesException;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    Lead::unsetEventDispatcher();

    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutMiddleware(Authenticate::class);
    $this->withoutVite();
});

function createSalesLeadForLead(int $leadId): void
{
    DB::table('salesleads')->insert([
        'name'              => 'Sales lead for '.$leadId,
        'pipeline_stage_id' => 1,
        'lead_id'           => $leadId,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
}

test('deleting a lead with a sales lead is rejected and the lead stays', function () {
    $lead = Lead::factory()->create();
    createSalesLeadForLead($lead->id);

    $this->deleteJson(route('admin.leads.delete', $lead->id))
        ->assertStatus(400)
        ->assertJsonPath('message', __('messages.lead.delete_blocked_sales', ['ids' => $lead->id]));

    expect(Lead::find($lead->id))->not->toBeNull()
        ->and(SalesLead::where('lead_id', $lead->id)->exists())->toBeTrue();
});

test('deleting a lead without a sales lead still works', function () {
    $lead = Lead::factory()->create();

    $this->deleteJson(route('admin.leads.delete', $lead->id))
        ->assertOk();

    expect(Lead::find($lead->id))->toBeNull();
});

test('mass delete is rejected entirely when any selected lead has a sales lead', function () {
    $withSales = Lead::factory()->create();
    $plain = Lead::factory()->create();
    createSalesLeadForLead($withSales->id);

    $this->postJson(route('admin.leads.mass_delete'), [
        'indices' => [$withSales->id, $plain->id],
    ])->assertStatus(400)
        ->assertJsonPath('message', __('messages.lead.delete_blocked_sales', ['ids' => $withSales->id]));

    expect(Lead::find($withSales->id))->not->toBeNull()
        ->and(Lead::find($plain->id))->not->toBeNull();
});

test('mass delete still works when none of the leads have a sales lead', function () {
    $first = Lead::factory()->create();
    $second = Lead::factory()->create();

    $this->postJson(route('admin.leads.mass_delete'), [
        'indices' => [$first->id, $second->id],
    ])->assertOk();

    expect(Lead::find($first->id))->toBeNull()
        ->and(Lead::find($second->id))->toBeNull();
});

test('repository delete throws when the lead has a sales lead', function () {
    $lead = Lead::factory()->create();
    createSalesLeadForLead($lead->id);

    expect(fn () => app(LeadRepository::class)->delete($lead->id))
        ->toThrow(CannotDeleteLeadWithSalesException::class);

    expect(Lead::find($lead->id))->not->toBeNull();
});

test('lead view hides the delete button when the lead has a sales lead', function () {
    $lead = Lead::factory()->create();
    createSalesLeadForLead($lead->id);

    $this->get(route('admin.leads.view', $lead->id))
        ->assertOk()
        ->assertDontSee('delete-url=', false);
});

test('lead view shows the delete button when the lead has no sales lead', function () {
    $lead = Lead::factory()->create();

    $this->get(route('admin.leads.view', $lead->id))
        ->assertOk()
        ->assertSee('delete-url=', false);
});
