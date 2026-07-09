<?php

use App\Models\Clinic;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopPerson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(), 'user');

    $this->clinic = Clinic::factory()->create();
    $this->invoice = InkoopInvoice::create([
        'clinic_id' => $this->clinic->id,
        'pdf_path'  => 'test/test.pdf',
    ]);
});

function makeInkoopPatient(InkoopInvoice $invoice, array $attrs = []): InkoopPerson
{
    return InkoopPerson::create(array_merge([
        'clinic_id'  => $invoice->clinic_id,
        'invoice_id' => $invoice->id,
        'firstname'  => 'Ritske',
        'lastname'   => 'Clewits',
        'crm_id'     => null,
    ], $attrs));
}

function step1MatchesFor($response, InkoopPerson $patient)
{
    return $response->viewData('patients')
        ->firstWhere('id', $patient->id)
        ->crm_matches
        ->pluck('id');
}

it('shows both duplicate CRM persons as matches for a patient', function () {
    $first = Person::factory()->create(['first_name' => 'Ritske', 'last_name' => 'Clewits']);
    $second = Person::factory()->create(['first_name' => 'Ritske', 'last_name' => 'Clewits']);

    $patient = makeInkoopPatient($this->invoice);

    $response = $this->get(route('admin.inkoop.step1', $this->invoice->id));
    $response->assertOk();

    $ids = step1MatchesFor($response, $patient);

    expect($ids->contains($first->id))->toBeTrue()
        ->and($ids->contains($second->id))->toBeTrue();
});

it('always keeps the linked person as an option even when it falls outside the name search', function () {
    // The linked CRM person has a completely different name, so the name search
    // would never return it — yet it must still appear so the user can see/change it.
    $linked = Person::factory()->create(['first_name' => 'Totally', 'last_name' => 'Different']);

    $patient = makeInkoopPatient($this->invoice, ['crm_id' => $linked->id]);

    $response = $this->get(route('admin.inkoop.step1', $this->invoice->id));
    $response->assertOk();

    expect(step1MatchesFor($response, $patient)->contains($linked->id))->toBeTrue();
});

it('saves the chosen CRM person id for a patient', function () {
    $person = Person::factory()->create();
    $patient = makeInkoopPatient($this->invoice);

    $this->put(route('admin.inkoop.save-crm-ids', $this->invoice->id), [
        'crm_ids' => [$patient->id => (string) $person->id],
    ])->assertRedirect();

    expect($patient->fresh()->crm_id)->toBe((string) $person->id);
});

it('resets a saved CRM coupling back to null', function () {
    $person = Person::factory()->create();
    $patient = makeInkoopPatient($this->invoice, ['crm_id' => $person->id]);

    $this->putJson(route('admin.inkoop.reset-person-crm-id', [$this->invoice->id, $patient->id]))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($patient->fresh()->crm_id)->toBeNull();
});
