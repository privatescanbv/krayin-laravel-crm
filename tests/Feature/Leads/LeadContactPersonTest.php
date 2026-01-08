<?php

use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');

    // Ensure we have a pipeline and stage
    $this->pipeline = Pipeline::first();
    $this->stage = Stage::first();
    if (! $this->pipeline || ! $this->stage) {
        throw new Exception('Pipeline or Stage not found. Ensure TestSeeder provisions them.');
    }
});

test('lead can have a contact person', function () {
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    $lead = Lead::factory()->create([
        'contact_person_id'      => $person->id,
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    expect($lead->contactPerson)->not->toBeNull()
        ->and($lead->contactPerson->id)->toBe($person->id)
        ->and($lead->contactPerson->first_name)->toBe('John')
        ->and($lead->contactPerson->last_name)->toBe('Doe');
});

test('lead contact person can be null', function () {
    $lead = Lead::factory()->create([
        'contact_person_id'      => null,
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    expect($lead->contactPerson)->toBeNull();
});

test('sales lead can have a contact person', function () {
    $person = Person::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => 'Smith',
    ]);

    $salesLead = SalesLead::factory()->create([
        'contact_person_id' => $person->id,
    ]);

    expect($salesLead->contactPerson)->not->toBeNull();
    expect($salesLead->contactPerson->id)->toBe($person->id);
    expect($salesLead->contactPerson->first_name)->toBe('Jane');
    expect($salesLead->contactPerson->last_name)->toBe('Smith');
});

test('sales lead contact person can be null', function () {
    $salesLead = SalesLead::factory()->create([
        'contact_person_id' => null,
    ]);

    expect($salesLead->contactPerson)->toBeNull();
});

test('lead contact person can be different from linked persons', function () {
    $contactPerson = Person::factory()->create([
        'first_name' => 'Contact',
        'last_name'  => 'Person',
    ]);

    $linkedPerson = Person::factory()->create([
        'first_name' => 'Linked',
        'last_name'  => 'Person',
    ]);

    $lead = Lead::factory()->create([
        'contact_person_id'      => $contactPerson->id,
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Attach a different person to the lead
    $lead->persons()->attach($linkedPerson->id);

    expect($lead->contactPerson->id)->toBe($contactPerson->id)
        ->and($lead->persons->first()->id)->toBe($linkedPerson->id)
        ->and($lead->contactPerson->id)->not->toBe($lead->persons->first()->id);
});

test('lead contact person can be the same as linked person', function () {
    $person = Person::factory()->create([
        'first_name' => 'Same',
        'last_name'  => 'Person',
    ]);

    $lead = Lead::factory()->create([
        'contact_person_id'      => $person->id,
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Attach the same person to the lead
    $lead->persons()->attach($person->id);

    expect($lead->contactPerson->id)->toBe($person->id)
        ->and($lead->persons->first()->id)->toBe($person->id)
        ->and($lead->contactPerson->id)->toBe($lead->persons->first()->id);
});
