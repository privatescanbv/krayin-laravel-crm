<?php

use App\Enums\ContactLabel;
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
