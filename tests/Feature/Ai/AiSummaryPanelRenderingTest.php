<?php

use App\Models\Order;
use App\Models\SalesLead;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');
});

test('the ai summary panel renders on every subject view', function (string $routeName, string $subjectKey) {
    $lead = Lead::factory()->create(['user_id' => $this->user->id]);
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id, 'user_id' => $this->user->id]);

    $id = match ($subjectKey) {
        'leads'       => $lead->id,
        'persons'     => Person::factory()->create()->id,
        'sales_leads' => $salesLead->id,
        'orders'      => Order::factory()->create([
            'sales_lead_id' => $salesLead->id,
            'user_id'       => $this->user->id,
        ])->id,
    };

    $this->get(route($routeName, $id))
        ->assertOk()
        ->assertSee('AI-samenvatting')
        ->assertSee(route('admin.ai-summary.show', [$subjectKey, $id]), false)
        // The refresh button posts here; without it the panel is read-only.
        ->assertSee(route('admin.ai-summary.generate', [$subjectKey, $id]), false)
        ->assertSee(':can-edit="true"', false)
        ->assertSee('Vernieuwen', false);
})->with([
    ['admin.leads.view', 'leads'],
    ['admin.contacts.persons.view', 'persons'],
    ['admin.sales-leads.view', 'sales_leads'],
    ['admin.orders.view', 'orders'],
]);
