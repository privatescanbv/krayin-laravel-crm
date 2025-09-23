<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Repositories\LeadRepository;

uses(RefreshDatabase::class);

test('updating lead with empty user_id sets it to null', function () {
    $lead = Lead::factory()->create();

    expect($lead->user_id)->not->toBeNull();

    $updated = app(LeadRepository::class)
        ->update(['user_id' => ''], $lead->id);

    expect($updated->user_id)->toBeNull();
    $this->assertDatabaseHas('leads', [
        'id' => $lead->id,
        'user_id' => null,
    ]);
});

test('it syncs persons even when user_id is cleared during update', function () {
    $lead = Lead::factory()->create();
    $personA = Person::factory()->create();
    $personB = Person::factory()->create();

    $payload = [
        'user_id' => '',
        'person_ids' => [$personA->id, $personB->id],
    ];

    $updated = app(LeadRepository::class)
        ->update($payload, $lead->id);

    expect($updated->user_id)->toBeNull()
        ->and($updated->fresh()->persons)->toHaveCount(2)
        ->and($updated->fresh()->persons->pluck('id')->contains($personA->id))->toBeTrue()
        ->and($updated->fresh()->persons->pluck('id')->contains($personB->id))->toBeTrue();
});

test('detaching the last person really unlinks the person and removes anamnesis', function () {
    $lead = Lead::factory()->create();
    $person = Person::factory()->create();

    // Attach single person
    $lead->attachPersons([$person->id]);
    expect($lead->fresh()->persons)->toHaveCount(1);

    // Detach via controller endpoint (simulating UI action)
    test()->deleteJson(route('admin.leads.detach_person', ['leadId' => $lead->id, 'personId' => $person->id]))
        ->assertOk();

    // Relationship should be removed
    expect($lead->fresh()->persons)->toHaveCount(0);

    // Anamnesis entry for this lead-person should be removed as well
    $exists = DB::table('anamneses')
        ->where('lead_id', $lead->id)
        ->where('person_id', $person->id)
        ->exists();
    expect($exists)->toBeFalse();
});

