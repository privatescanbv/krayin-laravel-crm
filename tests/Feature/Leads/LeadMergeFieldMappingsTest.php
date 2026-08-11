<?php

use App\Enums\MRIStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->leadRepository = app(LeadRepository::class);
});

test('it copies mri status, bsn, organization and contact person from the chosen duplicate', function () {
    $organization = Organization::factory()->create();
    $contactPerson = Person::factory()->create();

    $primaryLead = Lead::factory()->create([
        'mri_status'                     => MRIStatus::cases()[0],
        'national_identification_number' => '111222333',
        'organization_id'                => null,
        'contact_person_id'              => null,
    ]);

    $duplicateLead = Lead::factory()->create([
        'mri_status'                     => MRIStatus::cases()[1],
        'national_identification_number' => '999888777',
        'organization_id'                => $organization->id,
        'contact_person_id'              => $contactPerson->id,
    ]);

    $merged = $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id], [
        'mri_status'                     => $duplicateLead->id,
        'national_identification_number' => $duplicateLead->id,
        'organization_id'                => $duplicateLead->id,
        'contact_person_id'              => $duplicateLead->id,
    ]);

    expect($merged->mri_status)->toBe(MRIStatus::cases()[1])
        ->and($merged->national_identification_number)->toBe('999888777')
        ->and($merged->organization_id)->toBe($organization->id)
        ->and($merged->contact_person_id)->toBe($contactPerson->id);
});

test('it copies both diagnosis form columns as one choice', function () {
    $primaryLead = Lead::factory()->create([
        'diagnosis_form_id'    => 42,
        'diagnoseform_pdf_url' => null,
    ]);

    $duplicateLead = Lead::factory()->create([
        'diagnosis_form_id'    => null,
        'diagnoseform_pdf_url' => 'https://example.com/diagnose.pdf',
    ]);

    $merged = $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id], [
        'diagnosis_form' => $duplicateLead->id,
    ]);

    // The pair moves as a whole; keeping the primary's form id would produce a mix nobody chose.
    expect($merged->diagnosis_form_id)->toBeNull()
        ->and($merged->diagnoseform_pdf_url)->toBe('https://example.com/diagnose.pdf');
});

test('it keeps the primary value when the chosen duplicate has an empty field', function () {
    $organization = Organization::factory()->create();

    $primaryLead = Lead::factory()->create(['organization_id' => $organization->id]);
    $duplicateLead = Lead::factory()->create(['organization_id' => null]);

    $merged = $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id], [
        'organization_id' => $duplicateLead->id,
    ]);

    expect($merged->organization_id)->toBe($organization->id);
});

test('it keeps the contact person linked after the merge', function () {
    $contactPerson = Person::factory()->create();

    $primaryLead = Lead::factory()->create(['contact_person_id' => null]);
    $duplicateLead = Lead::factory()->create(['contact_person_id' => $contactPerson->id]);
    $duplicateLead->attachPersons([$contactPerson->id]);

    $merged = $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id], [
        'contact_person_id' => $duplicateLead->id,
    ]);

    expect($merged->contact_person_id)->toBe($contactPerson->id)
        ->and(DB::table('lead_persons')->where('lead_id', $merged->id)->pluck('person_id')->all())
        ->toBe([$contactPerson->id]);
});
