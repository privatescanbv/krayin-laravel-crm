<?php

use App\Enums\ContactLabel;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

test('lead duplicates merge view loads when primary lead has phone numbers', function () {
    $this->seed(TestSeeder::class);

    Lead::unsetEventDispatcher();

    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutMiddleware(Authenticate::class);

    $lead = Lead::factory()->create([
        'phones' => [
            ['value' => '+31651441908', 'label' => ContactLabel::Eigen->value],
        ],
    ]);

    $response = $this->get(route('admin.leads.duplicates.index', $lead->id));

    $response->assertOk();
});

test('the merge view exposes the extra selectable fields', function () {
    $this->seed(TestSeeder::class);

    Lead::unsetEventDispatcher();

    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutMiddleware(Authenticate::class);

    $organization = Organization::factory()->create();
    $contactPerson = Person::factory()->create();

    $lead = Lead::factory()->create([
        'organization_id'                => $organization->id,
        'contact_person_id'              => $contactPerson->id,
        'national_identification_number' => '123456789',
        'diagnosis_form_id'              => 42,
        'diagnoseform_pdf_url'           => 'https://example.com/diagnose.pdf',
    ]);

    $leadData = $this->get(route('admin.leads.duplicates.index', $lead->id))->viewData('leadData');

    expect($leadData['organization_name'])->toBe($organization->name)
        ->and($leadData['contact_person_name'])->toBe($contactPerson->name)
        ->and($leadData['national_identification_number'])->toBe('123456789')
        ->and($leadData['diagnosis_form'])->toBe('Formulier #42 + PDF');
});

test('the merge screen asks which anamnesis to keep and the merge applies that choice', function () {
    $this->seed(TestSeeder::class);

    Lead::unsetEventDispatcher();

    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutMiddleware(Authenticate::class);

    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create();
    $person = Person::factory()->create();

    $withGvl = Anamnesis::factory()->create(['lead_id' => $primary->id, 'person_id' => $person->id]);
    AnamnesisGvlForm::create(['anamnesis_id' => $withGvl->id, 'gvl_form_id' => '123', 'gvl_form_status' => 'completed']);
    $empty = Anamnesis::factory()->create(['lead_id' => $duplicate->id, 'person_id' => $person->id]);

    $conflicts = $this->get(route('admin.leads.duplicates.index', ['id' => $primary->id, 'with' => $duplicate->id]))
        ->viewData('anamnesisConflicts');

    $options = collect($conflicts)->firstWhere('person_id', $person->id)['options'];

    expect(collect($options)->pluck('id')->sort()->values()->all())->toBe(collect([$withGvl->id, $empty->id])->sort()->values()->all())
        ->and(collect($options)->firstWhere('id', $withGvl->id)['gvl_forms'][0]['completed'])->toBeTrue();

    $this->postJson(route('admin.leads.duplicates.merge', $primary->id), [
        'primary_lead_id'    => $primary->id,
        'duplicate_lead_ids' => [$duplicate->id],
        'anamnesis_choices'  => [$person->id => $withGvl->id],
    ])->assertOk()->assertJson(['success' => true]);

    expect($withGvl->fresh())->not->toBeNull()
        ->and($empty->fresh())->toBeNull();
});
