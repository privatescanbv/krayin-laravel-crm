s
<?php

use App\Enums\FormStatus;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Anamnesis\AnamnesisGvlFormResolver;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->resolver = app(AnamnesisGvlFormResolver::class);
});

// ---------------------------------------------------------------------------
// resolveForPerson
// ---------------------------------------------------------------------------

test('resolveForPerson returns order-level anamnesis when all three levels exist', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    $salesAnamnesis = Anamnesis::factory()->create(['sales_id' => $salesLead->id, 'lead_id' => null, 'person_id' => $person->id]);
    $orderAnamnesis = Anamnesis::factory()->create(['order_id' => $order->id, 'lead_id' => null, 'person_id' => $person->id]);

    $all = collect([$leadAnamnesis, $salesAnamnesis, $orderAnamnesis]);

    expect($this->resolver->resolveForPerson($all, $order->id, $person->id)?->id)
        ->toBe($orderAnamnesis->id);
});

test('resolveForPerson falls back to sales-level when no order-level anamnesis exists', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    $salesAnamnesis = Anamnesis::factory()->create(['sales_id' => $salesLead->id, 'lead_id' => null, 'person_id' => $person->id]);

    $all = collect([$leadAnamnesis, $salesAnamnesis]);

    expect($this->resolver->resolveForPerson($all, $order->id, $person->id)?->id)
        ->toBe($salesAnamnesis->id);
});

test('resolveForPerson falls back to lead-level when only lead-level exists', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);

    $all = collect([$leadAnamnesis]);

    expect($this->resolver->resolveForPerson($all, $order->id, $person->id)?->id)
        ->toBe($leadAnamnesis->id);
});

test('resolveForPerson returns null when no anamnesis exists for this person', function () {
    $person = Person::factory()->create();
    $otherPerson = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $anamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $otherPerson->id]);

    $all = collect([$anamnesis]);

    expect($this->resolver->resolveForPerson($all, $order->id, $person->id))->toBeNull();
});

// ---------------------------------------------------------------------------
// completedFormsForAnamnesis
// ---------------------------------------------------------------------------

test('completedFormsForAnamnesis returns only completed forms', function () {
    $lead = Lead::factory()->create();
    $person = Person::factory()->create();
    $anamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);

    AnamnesisGvlForm::create(['anamnesis_id' => $anamnesis->id, 'gvl_form_id' => '1', 'gvl_form_status' => FormStatus::Completed]);
    AnamnesisGvlForm::create(['anamnesis_id' => $anamnesis->id, 'gvl_form_id' => '2', 'gvl_form_status' => FormStatus::Step1_completed]);
    AnamnesisGvlForm::create(['anamnesis_id' => $anamnesis->id, 'gvl_form_id' => '3', 'gvl_form_status' => FormStatus::New]);

    $anamnesis->load('gvlForms');

    $forms = $this->resolver->completedFormsForAnamnesis($anamnesis);

    expect($forms)->toHaveCount(1);
    expect($forms->first()->gvl_form_id)->toBe('1');
});

test('completedFormsForAnamnesis returns multiple completed forms sorted newest first', function () {
    $lead = Lead::factory()->create();
    $person = Person::factory()->create();
    $anamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);

    $older = AnamnesisGvlForm::create(['anamnesis_id' => $anamnesis->id, 'gvl_form_id' => '10', 'gvl_form_status' => FormStatus::Completed]);
    $newer = AnamnesisGvlForm::create(['anamnesis_id' => $anamnesis->id, 'gvl_form_id' => '11', 'gvl_form_status' => FormStatus::Completed]);

    $anamnesis->load('gvlForms');

    $forms = $this->resolver->completedFormsForAnamnesis($anamnesis);

    expect($forms)->toHaveCount(2);
    expect($forms->first()->id)->toBe($newer->id);
});

test('completedFormsForAnamnesis returns empty collection for null anamnesis', function () {
    expect($this->resolver->completedFormsForAnamnesis(null)->isEmpty())->toBeTrue();
});

// ---------------------------------------------------------------------------
// loadForOrder
// ---------------------------------------------------------------------------

test('loadForOrder fetches anamnesis records from all three levels in one pass', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    $salesAnamnesis = Anamnesis::factory()->create(['sales_id' => $salesLead->id, 'lead_id' => null, 'person_id' => $person->id]);
    $orderAnamnesis = Anamnesis::factory()->create(['order_id' => $order->id, 'lead_id' => null, 'person_id' => $person->id]);

    // Unrelated anamnesis for a different lead — should not be returned
    Anamnesis::factory()->create(['lead_id' => Lead::factory()->create()->id, 'person_id' => $person->id]);

    $records = $this->resolver->loadForOrder($order);

    $ids = $records->pluck('id')->sort()->values();
    expect($ids)->toContain($leadAnamnesis->id)
        ->toContain($salesAnamnesis->id)
        ->toContain($orderAnamnesis->id)
        ->toHaveCount(3);
});

test('loadForOrder eager-loads gvlForms relation', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $anamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    AnamnesisGvlForm::create(['anamnesis_id' => $anamnesis->id, 'gvl_form_id' => '50', 'gvl_form_status' => FormStatus::Completed]);

    $records = $this->resolver->loadForOrder($order);

    expect($records->first()->relationLoaded('gvlForms'))->toBeTrue();
    expect($records->first()->gvlForms)->toHaveCount(1);
});
