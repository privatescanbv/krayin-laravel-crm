<?php

use App\Enums\FormStatus;
use App\Enums\FormType;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Anamnesis\AnamnesisFormsOverviewBuilder;
use App\Services\Anamnesis\AnamnesisGvlFormResolver;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Http;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->builder = app(AnamnesisFormsOverviewBuilder::class);
    $this->resolver = app(AnamnesisGvlFormResolver::class);
});

// ---------------------------------------------------------------------------
// Resolver: loadForSales / loadForLead
// ---------------------------------------------------------------------------

test('loadForSales fetches sales, parent lead and child order anamneses', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    $salesAnamnesis = Anamnesis::factory()->create(['sales_id' => $salesLead->id, 'lead_id' => null, 'person_id' => $person->id]);
    $orderAnamnesis = Anamnesis::factory()->create(['order_id' => $order->id, 'lead_id' => null, 'person_id' => $person->id]);

    Anamnesis::factory()->create(['lead_id' => Lead::factory()->create()->id, 'person_id' => $person->id]);

    $records = $this->resolver->loadForSales($salesLead);

    expect($records->pluck('id')->sort()->values()->all())
        ->toEqual(collect([$leadAnamnesis->id, $salesAnamnesis->id, $orderAnamnesis->id])->sort()->values()->all());
});

test('loadForLead fetches lead, downstream sales and order anamneses', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    $salesAnamnesis = Anamnesis::factory()->create(['sales_id' => $salesLead->id, 'lead_id' => null, 'person_id' => $person->id]);
    $orderAnamnesis = Anamnesis::factory()->create(['order_id' => $order->id, 'lead_id' => null, 'person_id' => $person->id]);

    Anamnesis::factory()->create(['lead_id' => Lead::factory()->create()->id, 'person_id' => $person->id]);

    $records = $this->resolver->loadForLead($lead);

    expect($records->pluck('id')->sort()->values()->all())
        ->toEqual(collect([$leadAnamnesis->id, $salesAnamnesis->id, $orderAnamnesis->id])->sort()->values()->all());
});

// ---------------------------------------------------------------------------
// Overview builder
// ---------------------------------------------------------------------------

it('order overview shows active form from most specific level', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    AnamnesisGvlForm::create([
        'anamnesis_id'    => $leadAnamnesis->id,
        'gvl_form_id'     => 'lead-gvl',
        'gvl_form_status' => FormStatus::Step1_completed,
        'gvl_form_type'   => FormType::PrivateScan,
    ]);

    $overview = $this->builder->buildForPerson($order, $person, 'order');

    expect($overview['active_forms'])->toHaveCount(1);
    expect($overview['active_forms'][0]['status'])->toBe('step1');
    expect($overview['active_forms'][0]['level'])->toBe('lead');
    expect($overview['inactive_forms'])->toBeEmpty();
    expect($overview['form_count'])->toBe(1);
});

test('inactive forms lists older forms at other levels', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    AnamnesisGvlForm::create([
        'anamnesis_id'    => $leadAnamnesis->id,
        'gvl_form_id'     => 'lead-gvl',
        'gvl_form_status' => FormStatus::New,
        'gvl_form_type'   => FormType::PrivateScan,
    ]);

    $salesAnamnesis = Anamnesis::factory()->create(['sales_id' => $salesLead->id, 'lead_id' => null, 'person_id' => $person->id]);
    AnamnesisGvlForm::create([
        'anamnesis_id'    => $salesAnamnesis->id,
        'gvl_form_id'     => 'sales-gvl',
        'gvl_form_status' => FormStatus::Step1_completed,
        'gvl_form_type'   => FormType::PrivateScan,
    ]);

    $orderAnamnesis = Anamnesis::factory()->create(['order_id' => $order->id, 'lead_id' => null, 'person_id' => $person->id]);
    AnamnesisGvlForm::create([
        'anamnesis_id'    => $orderAnamnesis->id,
        'gvl_form_id'     => 'order-gvl',
        'gvl_form_status' => FormStatus::Completed,
        'gvl_form_type'   => FormType::PrivateScan,
        'completed_at'    => now(),
    ]);

    $overview = $this->builder->buildForPerson($order, $person, 'order');

    expect($overview['form_count'])->toBe(3);
    expect($overview['active_forms'])->toHaveCount(1);
    expect($overview['active_forms'][0]['level'])->toBe('order');
    expect($overview['inactive_forms'])->toHaveCount(2);
    expect(collect($overview['inactive_forms'])->pluck('level')->sort()->values()->all())
        ->toEqual(['lead', 'sales']);
});

test('active form prefers order level over lead', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    AnamnesisGvlForm::create([
        'anamnesis_id'    => $leadAnamnesis->id,
        'gvl_form_id'     => 'lead-gvl',
        'gvl_form_status' => FormStatus::New,
        'gvl_form_type'   => FormType::PrivateScan,
    ]);

    $orderAnamnesis = Anamnesis::factory()->create(['order_id' => $order->id, 'lead_id' => null, 'person_id' => $person->id]);
    AnamnesisGvlForm::create([
        'anamnesis_id'    => $orderAnamnesis->id,
        'gvl_form_id'     => 'order-gvl',
        'gvl_form_status' => FormStatus::Completed,
        'gvl_form_type'   => FormType::PrivateScan,
        'completed_at'    => now(),
    ]);

    $overview = $this->builder->buildForPerson($order, $person, 'order');

    expect($overview['active_forms'])->toHaveCount(1);
    expect($overview['active_forms'][0]['level'])->toBe('order');
    expect($overview['active_forms'][0]['status'])->toBe('completed');
});

test('lead overview shows downstream order level GVL as active form', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $orderAnamnesis = Anamnesis::factory()->create(['order_id' => $order->id, 'lead_id' => null, 'person_id' => $person->id]);
    AnamnesisGvlForm::create([
        'anamnesis_id'    => $orderAnamnesis->id,
        'gvl_form_id'     => 'order-gvl',
        'gvl_form_status' => FormStatus::Completed,
        'gvl_form_type'   => FormType::PrivateScan,
        'completed_at'    => now(),
    ]);

    $overview = $this->builder->buildForPerson($lead, $person, 'lead');

    expect($overview['active_forms'])->toHaveCount(1);
    expect($overview['active_forms'][0]['level'])->toBe('order');
    expect($overview['active_forms'][0]['status'])->toBe('completed');
});

test('order overview excludes unrelated lead forms', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $unrelatedLead = Lead::factory()->create();
    $unrelatedAnamnesis = Anamnesis::factory()->create(['lead_id' => $unrelatedLead->id, 'person_id' => $person->id]);
    AnamnesisGvlForm::create([
        'anamnesis_id'    => $unrelatedAnamnesis->id,
        'gvl_form_id'     => 'other',
        'gvl_form_status' => FormStatus::New,
        'gvl_form_type'   => FormType::PrivateScan,
    ]);

    $overview = $this->builder->buildForPerson($order, $person, 'order');

    expect($this->builder->hasActiveFormOfType($overview, FormType::PrivateScan))->toBeFalse();
});

test('duplicate warnings when same type active on multiple levels', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    $salesAnamnesis = Anamnesis::factory()->create(['sales_id' => $salesLead->id, 'lead_id' => null, 'person_id' => $person->id]);

    foreach ([$leadAnamnesis, $salesAnamnesis] as $anamnesis) {
        AnamnesisGvlForm::create([
            'anamnesis_id'    => $anamnesis->id,
            'gvl_form_id'     => 'gvl-'.$anamnesis->id,
            'gvl_form_status' => FormStatus::New,
            'gvl_form_type'   => FormType::PrivateScan,
        ]);
    }

    $overview = $this->builder->buildForPerson($order, $person, 'order');

    expect($overview['duplicate_warnings'])->not->toBeEmpty();
    expect($overview['duplicate_warnings'][0]['type'])->toBe('privatescan');
});

test('hasActiveFormOfType returns false for different form types', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);

    $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    AnamnesisGvlForm::create([
        'anamnesis_id'    => $leadAnamnesis->id,
        'gvl_form_id'     => 'gvl-1',
        'gvl_form_status' => FormStatus::New,
        'gvl_form_type'   => FormType::PrivateScan,
    ]);

    $overview = $this->builder->buildForPerson($salesLead, $person, 'sales');

    expect($this->builder->hasActiveFormOfType($overview, FormType::PrivateScan))->toBeTrue();
    expect($this->builder->hasActiveFormOfType($overview, FormType::HerniaNarcoseForm))->toBeFalse();
});

test('chainsForPerson groups anamneses by lead chain and picks effective record', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);

    $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    $salesAnamnesis = Anamnesis::factory()->create(['sales_id' => $salesLead->id, 'lead_id' => null, 'person_id' => $person->id]);

    $chains = $this->builder->chainsForPerson($person);

    expect($chains)->toHaveCount(1);
    expect($chains->first()['entity_type'])->toBe('lead');
    expect($chains->first()['effective_anamnesis']->id)->toBe($salesAnamnesis->id);
    expect($chains->first()['lead']->id)->toBe($lead->id);
});

test('chainsForPerson returns separate chains for unrelated leads', function () {
    $person = Person::factory()->create();
    $leadA = Lead::factory()->create();
    $leadB = Lead::factory()->create();

    Anamnesis::factory()->create(['lead_id' => $leadA->id, 'person_id' => $person->id]);
    Anamnesis::factory()->create(['lead_id' => $leadB->id, 'person_id' => $person->id]);

    expect($this->builder->chainsForPerson($person))->toHaveCount(2);
});

test('chainsForPerson skips an anamnesis whose lead/sales/order was deleted', function () {
    $person = Person::factory()->create();

    $liveLead = Lead::factory()->create();
    Anamnesis::factory()->create(['lead_id' => $liveLead->id, 'person_id' => $person->id]);

    $deletedLead = Lead::factory()->create();
    Anamnesis::factory()->create(['lead_id' => $deletedLead->id, 'person_id' => $person->id]);
    $deletedLead->delete();

    $chains = $this->builder->chainsForPerson($person);

    expect($chains)->toHaveCount(1)
        ->and($chains->first()['lead']->id)->toBe($liveLead->id);
});

// ---------------------------------------------------------------------------
// Attach duplicate confirmation (HTTP)
// ---------------------------------------------------------------------------

describe('attach duplicate HTTP', function () {
    beforeEach(function () {
        test()->withoutMiddleware(CanInstall::class);
        test()->seed(TestSeeder::class);
        test()->actingAs(getDefaultAdmin(), 'user');

        config([
            'services.portal.patient.api_url'   => 'http://forms',
            'services.portal.patient.api_token' => 'test-token',
            'services.portal.patient.web_url'   => 'http://portal',
        ]);

        Http::fake([
            'http://forms/api/forms' => Http::response(['data' => ['id' => 9999], 'form_url' => 'http://portal/patient/forms/9999/step/1'], 201),
        ]);
    });

    test('attach GVL returns 409 when same type active on another level', function () {
        $person = Person::factory()->create(['keycloak_user_id' => 'kc-test']);
        $lead = Lead::factory()->create();
        $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);

        $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
        AnamnesisGvlForm::create([
            'anamnesis_id'    => $leadAnamnesis->id,
            'gvl_form_id'     => 'existing',
            'gvl_form_status' => FormStatus::New,
            'gvl_form_type'   => FormType::PrivateScan,
        ]);

        $salesAnamnesis = Anamnesis::factory()->create(['sales_id' => $salesLead->id, 'lead_id' => null, 'person_id' => $person->id]);

        test()->postJson(route('admin.anamnesis.gvl-form.attach', $salesAnamnesis->id), [
            'form_type' => FormType::PrivateScan->value,
        ])
            ->assertStatus(409)
            ->assertJson(['requires_confirmation' => true]);
    });

    test('attach GVL succeeds with force when same type active elsewhere', function () {
        $person = Person::factory()->create(['keycloak_user_id' => 'kc-test-2']);
        $lead = Lead::factory()->create();
        $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);

        $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
        AnamnesisGvlForm::create([
            'anamnesis_id'    => $leadAnamnesis->id,
            'gvl_form_id'     => 'existing-2',
            'gvl_form_status' => FormStatus::New,
            'gvl_form_type'   => FormType::PrivateScan,
        ]);

        $salesAnamnesis = Anamnesis::factory()->create(['sales_id' => $salesLead->id, 'lead_id' => null, 'person_id' => $person->id]);

        test()->postJson(route('admin.anamnesis.gvl-form.attach', $salesAnamnesis->id), [
            'form_type' => FormType::PrivateScan->value,
            'force'     => true,
        ])->assertOk();

        expect($salesAnamnesis->fresh()->gvlForms()->count())->toBe(1);
    });

    test('attach GVL does not warn for different form type', function () {
        $person = Person::factory()->create(['keycloak_user_id' => 'kc-test-3']);
        $lead = Lead::factory()->create();
        $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);

        $leadAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
        AnamnesisGvlForm::create([
            'anamnesis_id'    => $leadAnamnesis->id,
            'gvl_form_id'     => 'existing-3',
            'gvl_form_status' => FormStatus::New,
            'gvl_form_type'   => FormType::PrivateScan,
        ]);

        $salesAnamnesis = Anamnesis::factory()->create(['sales_id' => $salesLead->id, 'lead_id' => null, 'person_id' => $person->id]);

        test()->postJson(route('admin.anamnesis.gvl-form.attach', $salesAnamnesis->id), [
            'form_type' => FormType::HerniaNarcoseForm->value,
        ])->assertOk();
    });
});
