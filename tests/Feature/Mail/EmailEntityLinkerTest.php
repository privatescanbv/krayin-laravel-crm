<?php

use App\Models\Clinic;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Mail\EmailEntityLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create a pipeline stage that is neither won nor lost (i.e. "active").
 */
function activeStage(): Stage
{
    $pipeline = Pipeline::firstOrCreate(
        ['name' => 'Test Pipeline'],
        ['is_default' => 1, 'rotten_days' => 30]
    );

    return Stage::firstOrCreate(
        ['name' => 'Active', 'lead_pipeline_id' => $pipeline->id],
        ['code' => 'active', 'sort_order' => 1, 'is_won' => false, 'is_lost' => false]
    );
}

/**
 * Create a person with a specific email address.
 */
function personWithEmail(string $email): Person
{
    return Person::factory()->create([
        'emails' => [['value' => $email, 'label' => 'work', 'is_default' => true]],
    ]);
}

/**
 * Create an active SalesLead and attach the given person.
 */
function activeSalesLeadForPerson(Person $person): SalesLead
{
    $stage = activeStage();
    $salesLead = SalesLead::factory()->create(['pipeline_stage_id' => $stage->id]);
    $salesLead->persons()->attach($person->id);

    return $salesLead;
}

/**
 * Create an active Order linked to the given SalesLead (and therefore person).
 */
function activeOrderForSalesLead(SalesLead $salesLead): Order
{
    $stage = activeStage();

    return Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $stage->id,
    ]);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test('links to active order when person has one', function () {
    $person = personWithEmail('patient@example.com');
    $salesLead = activeSalesLeadForPerson($person);
    $order = activeOrderForSalesLead($salesLead);

    $result = app(EmailEntityLinker::class)->link([], 'patient@example.com');

    expect($result)->toHaveKey('order_id', $order->id)
        ->not->toHaveKey('sales_lead_id')
        ->not->toHaveKey('person_id');
});

test('order takes priority over active sales lead', function () {
    $person = personWithEmail('patient@example.com');
    $salesLead = activeSalesLeadForPerson($person);
    $order = activeOrderForSalesLead($salesLead);

    // Create a second active sales lead directly
    $otherSalesLead = activeSalesLeadForPerson($person);

    $result = app(EmailEntityLinker::class)->link([], 'patient@example.com');

    expect($result)->toHaveKey('order_id', $order->id)
        ->not->toHaveKey('sales_lead_id')
        ->not->toHaveKey('person_id');
});

test('links to active sales lead when person has no active order', function () {
    $person = personWithEmail('patient@example.com');
    $salesLead = activeSalesLeadForPerson($person);

    $result = app(EmailEntityLinker::class)->link([], 'patient@example.com');

    expect($result)->toHaveKey('sales_lead_id', $salesLead->id)
        ->not->toHaveKey('order_id')
        ->not->toHaveKey('person_id');
});

test('links to active lead only when no active order or sales lead', function () {
    $stage = activeStage();
    $person = personWithEmail('patient@example.com');

    $lead = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);
    $lead->persons()->attach($person->id);

    $result = app(EmailEntityLinker::class)->link([], 'patient@example.com');

    expect($result)->toHaveKey('lead_id', $lead->id)
        ->not->toHaveKey('person_id')
        ->not->toHaveKey('order_id')
        ->not->toHaveKey('sales_lead_id');
});

test('links to person only when no active entities exist', function () {
    $person = personWithEmail('patient@example.com');

    $result = app(EmailEntityLinker::class)->link([], 'patient@example.com');

    expect($result)->toHaveKey('person_id', $person->id)
        ->not->toHaveKey('order_id')
        ->not->toHaveKey('sales_lead_id')
        ->not->toHaveKey('lead_id');
});

test('links to lead directly when no person found but lead has matching email', function () {
    $stage = activeStage();
    $lead = Lead::factory()->create([
        'lead_pipeline_stage_id' => $stage->id,
        'emails'                 => [['value' => 'lead@example.com', 'label' => 'work', 'is_default' => true]],
    ]);

    $result = app(EmailEntityLinker::class)->link([], 'lead@example.com');

    expect($result)->toHaveKey('lead_id', $lead->id)
        ->not->toHaveKey('person_id');
});

test('returns empty array when email address is empty', function () {
    $result = app(EmailEntityLinker::class)->link(['subject' => 'test'], '');

    expect($result)->toBe(['subject' => 'test']);
});

test('links to clinic when email matches a clinic but no person', function () {
    $clinic = Clinic::factory()->create([
        'emails' => [['value' => 'clinic@example.com', 'is_default' => true]],
    ]);

    $result = app(EmailEntityLinker::class)->link([], 'clinic@example.com');

    expect($result)->toHaveKey('clinic_id', $clinic->id)
        ->not->toHaveKey('person_id')
        ->not->toHaveKey('order_id')
        ->not->toHaveKey('sales_lead_id');
});
