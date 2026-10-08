<?php

use App\Actions\Leads\SnapshotLeadCustomerTypeAction;
use App\Enums\CustomerType;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\User;

function snapshotPriorLead(Person $person): Lead
{
    $lead = Lead::factory()->create(['created_at' => now()->subYear()]);
    $lead->attachPersons([$person->id]);

    return $lead;
}

function snapshotPurchase(Person $person, string $closedAt): void
{
    $won = Stage::factory()->won()->create(['lead_pipeline_id' => Pipeline::factory()->create()->id]);
    $order = Order::factory()->create(['pipeline_stage_id' => $won->id, 'closed_at' => $closedAt]);
    OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => $person->id]);
}

test('linking a person without history marks the lead as new', function () {
    $lead = Lead::factory()->create();
    $lead->attachPersons([Person::factory()->create()->id]);

    $lead->refresh();

    expect($lead->customer_type)->toBe(CustomerType::New)
        ->and($lead->prior_lead_count)->toBe(0)
        ->and($lead->prior_purchase_count)->toBe(0)
        ->and($lead->customer_type_determined_at)->not->toBeNull();
});

test('a lead without persons has no customer type', function () {
    expect(Lead::factory()->create()->refresh()->customer_type)->toBeNull();
});

test('a returning person without purchases is an existing non-buyer', function () {
    $person = Person::factory()->create();
    snapshotPriorLead($person);
    snapshotPriorLead($person);

    $lead = Lead::factory()->create();
    $lead->attachPersons([$person->id]);

    expect($lead->refresh()->customer_type)->toBe(CustomerType::ExistingNonBuyer)
        ->and($lead->prior_lead_count)->toBe(2);
});

test('an earlier buyer is an existing buyer with purchase count and last purchase date', function () {
    $person = Person::factory()->create();
    snapshotPurchase($person, now()->subMonths(8)->toDateString());

    $lead = Lead::factory()->create();
    $lead->attachPersons([$person->id]);

    expect($lead->refresh()->customer_type)->toBe(CustomerType::ExistingBuyer)
        ->and($lead->prior_purchase_count)->toBe(1)
        ->and($lead->last_purchase_at->toDateString())->toBe(now()->subMonths(8)->toDateString());
});

test('a purchase after the lead came in does not rewrite the snapshot', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create(['created_at' => now()->subMonth()]);
    $lead->attachPersons([$person->id]);

    snapshotPurchase($person, now()->toDateString());
    app(SnapshotLeadCustomerTypeAction::class)->execute($lead);

    expect($lead->refresh()->customer_type)->toBe(CustomerType::New);
});

test('with several persons the strongest history wins', function () {
    $newcomer = Person::factory()->create();
    $buyer = Person::factory()->create();
    snapshotPurchase($buyer, now()->subYear()->toDateString());

    $lead = Lead::factory()->create();
    $lead->attachPersons([$newcomer->id, $buyer->id]);

    expect($lead->refresh()->customer_type)->toBe(CustomerType::ExistingBuyer);
});

test('detaching a person through the relation re-derives the type', function () {
    $buyer = Person::factory()->create();
    snapshotPurchase($buyer, now()->subYear()->toDateString());
    $newcomer = Person::factory()->create();

    $lead = Lead::factory()->create();
    $lead->attachPersons([$newcomer->id, $buyer->id]);
    $lead->persons()->detach($buyer->id);

    expect($lead->refresh()->customer_type)->toBe(CustomerType::New);
});

test('syncing to no persons clears the type', function () {
    $lead = Lead::factory()->create();
    $lead->attachPersons([Person::factory()->create()->id]);

    $lead->syncPersons([]);

    expect($lead->refresh()->customer_type)->toBeNull()
        ->and($lead->customer_type_determined_at)->toBeNull();
});

test('detaching a person from the lead screen re-derives the type', function () {
    $buyer = Person::factory()->create();
    snapshotPurchase($buyer, now()->subYear()->toDateString());

    $lead = Lead::factory()->create();
    $lead->attachPersons([$buyer->id]);
    expect($lead->refresh()->customer_type)->toBe(CustomerType::ExistingBuyer);

    test()->actingAs(User::factory()->active()->create(), 'user')
        ->deleteJson(route('admin.leads.detach_person', ['leadId' => $lead->id, 'personId' => $buyer->id]))
        ->assertOk();

    expect($lead->refresh()->customer_type)->toBeNull();
});

test('backfill dry-run writes nothing, a real run classifies every lead', function () {
    $person = Person::factory()->create();
    snapshotPriorLead($person);
    $lead = Lead::factory()->create();
    $lead->attachPersons([$person->id]);

    // Simulate leads from before this feature existed.
    DB::table('leads')->update(['customer_type' => null, 'customer_type_determined_at' => null]);

    $this->artisan('leads:backfill-customer-type', ['--dry-run' => true])->assertSuccessful();
    expect(Lead::whereNotNull('customer_type')->count())->toBe(0);

    $this->artisan('leads:backfill-customer-type', ['--chunk' => 1])->assertSuccessful();

    expect($lead->refresh()->customer_type)->toBe(CustomerType::ExistingNonBuyer)
        ->and(Lead::whereNull('customer_type')->count())->toBe(0);
});

test('merging a duplicate person into a buyer re-derives the leads of that person', function () {
    $buyer = Person::factory()->create();
    snapshotPurchase($buyer, now()->subYear()->toDateString());

    // The same patient registered twice: the new lead landed on the duplicate.
    $duplicate = Person::factory()->create();
    $lead = Lead::factory()->create();
    $lead->attachPersons([$duplicate->id]);
    expect($lead->refresh()->customer_type)->toBe(CustomerType::New);

    app(PersonRepository::class)->mergePersons($buyer->id, [$duplicate->id]);

    expect($lead->refresh()->customer_type)->toBe(CustomerType::ExistingBuyer);
});

test('a merged-away duplicate lead no longer counts as a prior lead', function () {
    $person = Person::factory()->create();
    $primary = snapshotPriorLead($person);
    $duplicate = snapshotPriorLead($person);

    $later = Lead::factory()->create();
    $later->attachPersons([$person->id]);
    expect($later->refresh()->prior_lead_count)->toBe(2);

    app(LeadRepository::class)->mergeLeads($primary->id, [$duplicate->id]);

    expect($later->refresh()->prior_lead_count)->toBe(1);
});
