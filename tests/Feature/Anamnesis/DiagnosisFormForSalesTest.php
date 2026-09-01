<?php

namespace Tests\Feature\Anamnesis;

use App\Enums\ActivityType;
use App\Enums\ContactLabel;
use App\Enums\Departments;
use App\Enums\FormStatus;
use App\Enums\FormType;
use App\Enums\NotificationReferenceType;
use App\Events\PatientFormCompletedEvent;
use App\Events\PatientFormStatusUpdatedEvent;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Models\Department;
use App\Models\PatientNotification;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);
    $this->actingAs(getDefaultAdmin(), 'user');

    config([
        'services.portal.patient.api_url'   => 'http://forms',
        'services.portal.patient.api_token' => 'test-token',
        'services.portal.patient.web_url'   => 'http://portal',
    ]);

    Http::fake([
        'http://forms/api/forms'   => Http::response(['data' => ['id' => 4321], 'form_url' => 'http://portal/patient/forms/4321/step/1'], 201),
        'http://forms/api/forms/*' => Http::response([], 200),
    ]);
});

/**
 * @param  'hernia'|'privatescan'  $dept
 */
function makeSaleWithPatient(string $dept = 'hernia', bool $withPortalAccount = true): array
{
    $department = Department::firstOrCreate([
        'name' => $dept === 'hernia' ? Departments::HERNIA->value : Departments::PRIVATESCAN->value,
    ]);

    $person = Person::factory()->create([
        'emails'           => [['value' => 'patient@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
        'keycloak_user_id' => $withPortalAccount ? 'kc-'.uniqid() : null,
    ]);

    $lead = Lead::factory()->create(['department_id' => $department->id]);
    $lead->attachPersons([$person->id]);

    $salesLead = SalesLead::factory()->withLead($lead)->create();
    $salesLead->attachPersons([$person->id]);

    return [$salesLead, $person];
}

function attachDiagnosis(SalesLead $salesLead, Person $person, string $formType): TestResponse
{
    return test()->post(route('admin.anamnesis.diagnosis-form.attach'), [
        'sales_id'  => $salesLead->id,
        'person_id' => $person->id,
        'form_type' => $formType,
    ]);
}

it('sets up a diagnose form from a Herniapoli sale for an existing patient', function (string $formType) {
    [$salesLead, $person] = makeSaleWithPatient();

    attachDiagnosis($salesLead, $person, $formType)->assertRedirect();

    $anamnesis = Anamnesis::where('sales_id', $salesLead->id)->where('person_id', $person->id)->first();
    expect($anamnesis)->not->toBeNull();

    $form = $anamnesis->gvlForms()->first();
    expect($form)->not->toBeNull()
        ->and($form->gvl_form_type)->toBe(FormType::from($formType))
        ->and($form->gvl_form_id)->toBe('4321');

    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && str_contains($r->url(), 'http://forms/api/forms')
        && ($r->data()['form_type'] ?? null) === $formType);

    $notification = PatientNotification::where('patient_id', $person->id)
        ->where('reference_type', NotificationReferenceType::DIAGNOSIS_FORM)
        ->first();
    expect($notification)->not->toBeNull()
        ->and($notification->title)->toBe('patient_notifications.diagnosis.title');

    // no new lead was created
    expect(Lead::count())->toBe(1);
})->with([
    FormType::HerniaBackPainForm->value,
    FormType::HerniaNeckPainForm->value,
]);

it('allows both diagnose form types on the same patient side by side', function () {
    [$salesLead, $person] = makeSaleWithPatient();

    attachDiagnosis($salesLead, $person, FormType::HerniaBackPainForm->value)->assertRedirect();
    Http::fake([
        'http://forms/api/forms'   => Http::response(['data' => ['id' => 9999], 'form_url' => 'http://portal/patient/forms/9999/step/1'], 201),
        'http://forms/api/forms/*' => Http::response([], 200),
    ]);
    attachDiagnosis($salesLead, $person, FormType::HerniaNeckPainForm->value)->assertRedirect();

    $anamnesis = Anamnesis::where('sales_id', $salesLead->id)->where('person_id', $person->id)->first();

    expect($anamnesis->gvlForms()->pluck('gvl_form_type')->map->value->sort()->values()->all())
        ->toBe([FormType::HerniaBackPainForm->value, FormType::HerniaNeckPainForm->value]);
});

it('refuses a diagnose form for a non-Herniapoli sale', function () {
    [$salesLead, $person] = makeSaleWithPatient('privatescan');

    attachDiagnosis($salesLead, $person, FormType::HerniaBackPainForm->value)->assertForbidden();

    expect(AnamnesisGvlForm::count())->toBe(0);
    // No diagnose form pushed to the portal. Scoped to the forms API so an unrelated
    // stray request from a sibling test in the same parallel worker can't fail this.
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/api/forms'));
});

it('refuses a diagnose form when the patient has no portal account', function () {
    [$salesLead, $person] = makeSaleWithPatient('hernia', withPortalAccount: false);

    attachDiagnosis($salesLead, $person, FormType::HerniaBackPainForm->value)
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(AnamnesisGvlForm::count())->toBe(0);
    // No diagnose form pushed to the portal. Scoped to the forms API so an unrelated
    // stray request from a sibling test in the same parallel worker can't fail this.
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/api/forms'));
});

it('rejects a non-diagnose form_type on the diagnosis-form route', function () {
    [$salesLead, $person] = makeSaleWithPatient();

    attachDiagnosis($salesLead, $person, FormType::PrivateScan->value)->assertSessionHasErrors('form_type');
    attachDiagnosis($salesLead, $person, FormType::HerniaDiagnosisForm->value)->assertSessionHasErrors('form_type');

    expect(AnamnesisGvlForm::count())->toBe(0);
});

it('blocks a diagnose form_type on the generic gvl-form attach route for a lead anamnesis', function () {
    $lead = Lead::factory()->create();
    $person = Person::factory()->create([
        'emails'           => [['value' => 'x@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
        'keycloak_user_id' => 'kc-x',
    ]);
    $lead->attachPersons([$person->id]);
    $anamnesis = Anamnesis::where('lead_id', $lead->id)->where('person_id', $person->id)->firstOrFail();

    test()->postJson(route('admin.anamnesis.gvl-form.attach', $anamnesis->id), [
        'form_type' => FormType::HerniaBackPainForm->value,
    ])->assertStatus(500);

    expect($anamnesis->gvlForms()->count())->toBe(0);
});

it('processes the completion webhook and cleans up the diagnose notification', function () {
    [$salesLead, $person] = makeSaleWithPatient();
    attachDiagnosis($salesLead, $person, FormType::HerniaNeckPainForm->value)->assertRedirect();

    expect(PatientNotification::where('reference_type', NotificationReferenceType::DIAGNOSIS_FORM)->count())->toBe(1);

    PatientFormCompletedEvent::dispatch($person, '4321', FormType::HerniaNeckPainForm);
    PatientFormStatusUpdatedEvent::dispatch('4321', FormStatus::Completed, FormType::HerniaNeckPainForm);

    expect(AnamnesisGvlForm::where('gvl_form_id', '4321')->first()->gvl_form_status)->toBe(FormStatus::Completed)
        ->and(PatientNotification::where('reference_type', NotificationReferenceType::DIAGNOSIS_FORM)->count())->toBe(0)
        ->and(Activity::where('type', ActivityType::TASK)->where('title', 'like', '%Diagnose nekpijn%')->exists())->toBeTrue();
});

it('shows a forms overview with both diagnose types on a Herniapoli sale', function () {
    [$salesLead] = makeSaleWithPatient();

    test()->get(route('admin.sales-leads.view', $salesLead->id))
        ->assertOk()
        ->assertSee('Formulieren')
        ->assertSee('Diagnose lage rugpijn')
        ->assertSee('Diagnose nekpijn')
        ->assertSee('Koppel formulier');
});

it('does not show the diagnose block on a non-Herniapoli sale', function () {
    [$salesLead] = makeSaleWithPatient('privatescan');

    test()->get(route('admin.sales-leads.view', $salesLead->id))
        ->assertOk()
        ->assertDontSee('Diagnose lage rugpijn')
        ->assertDontSee('Diagnose nekpijn');
});

it('shows status and a view link once a diagnose form is set up', function () {
    [$salesLead, $person] = makeSaleWithPatient();
    attachDiagnosis($salesLead, $person, FormType::HerniaBackPainForm->value)->assertRedirect();

    test()->get(route('admin.sales-leads.view', $salesLead->id))
        ->assertOk()
        ->assertSee('Bekijken')
        ->assertSee('Ontkoppel')
        ->assertSee('Diagnose nekpijn');
});

it('refuses a second form of the same diagnose type', function () {
    [$salesLead, $person] = makeSaleWithPatient();

    attachDiagnosis($salesLead, $person, FormType::HerniaBackPainForm->value)->assertRedirect();
    attachDiagnosis($salesLead, $person, FormType::HerniaBackPainForm->value)
        ->assertRedirect()
        ->assertSessionHas('warning');

    $anamnesis = Anamnesis::where('sales_id', $salesLead->id)->where('person_id', $person->id)->first();
    expect($anamnesis->gvlForms()->where('gvl_form_type', FormType::HerniaBackPainForm->value)->count())->toBe(1);
});

it('does not list a diagnose form in the GVL Formulieren block', function () {
    [$salesLead, $person] = makeSaleWithPatient();
    attachDiagnosis($salesLead, $person, FormType::HerniaBackPainForm->value)->assertRedirect();

    $anamnesis = Anamnesis::where('sales_id', $salesLead->id)->where('person_id', $person->id)->firstOrFail();

    $html = view('adminc.anamnesis.gvl-forms-list', ['anamnesis' => $anamnesis, 'personHasPortalAccount' => true])->render();

    expect($html)->toContain('Geen GVL formulier gekoppeld.')
        ->not->toContain('Diagnose lage rugpijn');
});

it('FormType::isGvlForm separates GVL/Narcose from diagnose forms', function () {
    expect(FormType::PrivateScan->isGvlForm())->toBeTrue()
        ->and(FormType::HerniaNarcoseForm->isGvlForm())->toBeTrue()
        ->and(FormType::HerniaBackPainForm->isGvlForm())->toBeFalse()
        ->and(FormType::HerniaNeckPainForm->isGvlForm())->toBeFalse()
        ->and(FormType::manualCases())->not->toContain(FormType::HerniaBackPainForm);
});

it('detaches a diagnose form via the diagnosis-form detach route', function () {
    [$salesLead, $person] = makeSaleWithPatient();
    attachDiagnosis($salesLead, $person, FormType::HerniaNeckPainForm->value)->assertRedirect();

    $form = AnamnesisGvlForm::firstOrFail();

    test()->post(route('admin.anamnesis.diagnosis-form.detach'), ['gvl_form_record_id' => $form->id])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(AnamnesisGvlForm::count())->toBe(0);
    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'http://forms/api/forms/'));
});

it('writes a changelog activity naming the diagnose form type', function () {
    [$salesLead, $person] = makeSaleWithPatient();

    attachDiagnosis($salesLead, $person, FormType::HerniaBackPainForm->value)->assertRedirect();

    expect(
        Activity::where('type', ActivityType::SYSTEM)
            ->where('title', 'like', 'Diagnose lage rugpijn aangemaakt:%')
            ->exists()
    )->toBeTrue();
});
