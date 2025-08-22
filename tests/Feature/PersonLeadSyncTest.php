<?php

use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\User;

beforeEach(function () {
    test()->personRepository = app(PersonRepository::class);
    test()->leadRepository = app(LeadRepository::class);

    // Create a test user with active status and proper role
    test()->user = User::factory()->active()->create();

});

// Helper to get required pipeline/stage data and ensure authentication
function createPipelineData(): array
{
    // Create pipeline and stage if not exists
    $pipeline = Pipeline::firstOrCreate([
        'name'        => 'Test Pipeline',
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);

    $stage = Stage::firstOrCreate([
        'name'             => 'New',
        'code'             => 'new',
        'lead_pipeline_id' => $pipeline->id,
        'sort_order'       => 1,
    ]);

    return ['pipelineId' => $pipeline->id, 'stageId' => $stage->id];
}

test('can access edit with lead page', function () {
    $data = createPipelineData();
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john@example.com', 'label' => 'Work']],
        'user_id'    => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    // Attach the person to the lead
    $lead->persons()->attach($person->id);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson(route('admin.contacts.persons.edit_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]));

    $response->assertOk();
    $response->assertViewIs('admin::contacts.persons.edit-with-lead');
    $response->assertViewHas('person', $person);
    $response->assertViewHas('lead', $lead);
});

test('manual search returns no results when no person_id provided', function () {
    $data = createPipelineData();

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson('/admin/contacts/persons/search?'.http_build_query([
            'search'  => 'John',
            'lead_id' => $lead->id,
        ]));

    $response->assertOk();
    $data = $response->json();
    expect($data['data'])->toBeArray()->toBeEmpty();
});

test('manual search returns exact match when exact data provided', function () {
    $data = createPipelineData();
    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'        => [['value' => '123456789', 'label' => 'Mobile']],
        'date_of_birth' => '1985-05-15',
        'user_id'       => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
        // Set lead personal fields to match the search criteria
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'emails'                 => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'                 => [['value' => '123456789', 'label' => 'Mobile']],
        'date_of_birth'          => '1985-05-15',
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson('/admin/contacts/persons/search?'.http_build_query([
            'search'        => 'John',
            'lead_id'       => $lead->id,
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
            'phones'        => [['value' => '123456789', 'label' => 'Mobile']],
            'date_of_birth' => '1985-05-15',
        ]));

    $response->assertOk();
    $data = $response->json();
    expect($data['data'])->toBeArray()->toHaveCount(1);
    expect($data['data'][0]['score'])->toBe(100.0);
    expect($data['data'][0]['name'])->toBe('John Doe');
});

test('can update person with lead data', function () {
    $data = createPipelineData();
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john@example.com', 'label' => 'Work']],
        'user_id'    => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    test()
        ->actingAs(test()->user, 'user')
        ->postJson(route('admin.contacts.persons.update_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]), [
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
            'emails'     => [['value' => 'jane.smith@example.com', 'label' => 'Work']],
        ])->assertOk();

    $person->refresh();
    expect($person->first_name)->toBe('Jane');
    expect($person->last_name)->toBe('Smith');
    expect($person->emails[0]['value'])->toBe('jane.smith@example.com');
});

test('manual search returns partial match', function () {
    $data = createPipelineData();
    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'        => [['value' => '123456789', 'label' => 'Mobile']],
        'date_of_birth' => '1985-05-15',
        'user_id'       => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
        // Set lead personal fields to match the search criteria
        'first_name'             => 'John',
        'last_name'              => 'Smith', // Different from person for partial match
        'emails'                 => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'                 => [['value' => '123456789', 'label' => 'Mobile']],
        'date_of_birth'          => '1985-05-15',
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson('/admin/contacts/persons/search?'.http_build_query([
            'search'        => 'John',
            'lead_id'       => $lead->id,
            'first_name'    => 'John',
            'last_name'     => 'Smith', // Different last name
            'emails'        => [['value' => 'john@example.com', 'label' => 'Work']], // Same email
            'phones'        => [['value' => '123456789', 'label' => 'Mobile']], // Same phone
            'date_of_birth' => '1985-05-15', // Same birth date
        ]));

    $response->assertOk();
    $data = $response->json();
    expect($data['data'])->toBeArray()->toHaveCount(1);
    expect($data['data'][0]['score'])->toBeGreaterThan(0);
    expect($data['data'][0]['score'])->toBeLessThan(100);
});

test('handles validation errors gracefully', function () {
    $data = createPipelineData();
    $person = Person::factory()->create([
        'user_id' => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    // Test with invalid person ID
    $response = test()
        ->actingAs(test()->user, 'user')
        ->post(route('admin.contacts.persons.update_with_lead', [
            'personId' => 99999,
            'leadId'   => $lead->id,
        ]), [
            'first_name' => 'Jane',
        ]);

    $response->assertStatus(404);
});

test('returns correct field differences', function () {
    $data = createPipelineData();
    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'        => [['value' => '123456789', 'label' => 'Mobile']],
        'date_of_birth' => '1990-01-01',
        'user_id'       => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson(route('admin.contacts.persons.edit_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]));

    $response->assertOk();
    $response->assertViewHas('fieldDifferences');
});

test('handles malformed date gracefully', function () {
    $data = createPipelineData();
    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'date_of_birth' => '1990-13-40', // Invalid date
        'user_id'       => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    // Refresh person to get the malformed date
    $person = $person->fresh();

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson(route('admin.contacts.persons.edit_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]));

    $response->assertOk();
});

test('handles null date values correctly', function () {
    $data = createPipelineData();
    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'date_of_birth' => null,
        'user_id'       => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson(route('admin.contacts.persons.edit_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]));

    $response->assertOk();
});

test('manual search returns match scores when lead_id provided', function () {
    $data = createPipelineData();

    // Create persons with different similarity levels
    $perfectMatchPerson = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'        => [['value' => '123456789', 'label' => 'Mobile']],
        'date_of_birth' => '1985-05-15',
        'user_id'       => test()->user->id,
    ]);

    $differentDataPerson = Person::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => 'Smith',
        'emails'     => [['value' => 'jane@example.com', 'label' => 'Work']],
        'user_id'    => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
        // Set lead personal fields to match the search criteria
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'emails'                 => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'                 => [['value' => '123456789', 'label' => 'Mobile']],
        'date_of_birth'          => '1985-05-15',
    ]);

    test()
        ->actingAs(test()->user, 'user')
        ->postJson(route('admin.contacts.persons.update_with_lead', [
            'personId' => $perfectMatchPerson->id,
            'leadId'   => $lead->id,
        ]))->assertOk();

    $partialMatchPerson = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Smith', // Different last name for partial match
        'emails'     => [['value' => 'john@example.com', 'label' => 'Work']], // Same email
        'user_id'    => test()->user->id,
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson('/admin/contacts/persons/search?'.http_build_query([
            'search'        => 'John',
            'lead_id'       => $lead->id,
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
            'phones'        => [['value' => '123456789', 'label' => 'Mobile']],
            'date_of_birth' => '1985-05-15',
        ]));

    $response->assertOk();
    $data = $response->json();
    expect($data['data'])->toBeArray();

    // Should find the partial match person
    $foundPartialMatch = collect($data['data'])->firstWhere('id', $partialMatchPerson->id);
    expect($foundPartialMatch)->not->toBeNull();
    expect($foundPartialMatch['score'])->toBeGreaterThan(0);
});

test('manual search handles exact match correctly', function () {
    $data = createPipelineData();

    $exactMatchPerson = Person::factory()->create([
        'name'                => 'John Doe', // Override factory name
        'first_name'          => 'John',
        'last_name'           => 'Doe',
        'lastname_prefix'     => null, // Ensure no extra name fields
        'married_name'        => null,
        'married_name_prefix' => null,
        'initials'            => null,
        'emails'              => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'              => [['value' => '123456789', 'label' => 'Mobile']],
        'contact_numbers'     => [['value' => '123456789', 'label' => 'Mobile']], // Match factory format
        'date_of_birth'       => '1985-05-15',
        'user_id'             => test()->user->id,
    ]);

    $partialMatchPerson = Person::factory()->create([
        'name'        => 'John Smith', // Override factory name
        'first_name'  => 'John',
        'last_name'   => 'Smith',
        'emails'      => [['value' => 'john.smith@example.com', 'label' => 'Work']],
        'contact_numbers' => [['value' => '987654321', 'label' => 'Mobile']], // Different phone
        'user_id'     => test()->user->id,
    ]);

    $differentAddressPerson = Person::factory()->create([
        'name'        => 'John Doe', // Override factory name  
        'first_name'  => 'John',
        'last_name'   => 'Doe',
        'emails'      => [['value' => 'john@example.com', 'label' => 'Work']],
        'contact_numbers' => [['value' => '555666777', 'label' => 'Mobile']], // Different phone
        'user_id'     => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
        // Set lead personal fields to exactly match the person
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'lastname_prefix'        => null, // Ensure no extra name fields
        'married_name'           => null,
        'married_name_prefix'    => null,
        'initials'               => null,
        'emails'                 => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'                 => [['value' => '123456789', 'label' => 'Mobile']],
        'date_of_birth'          => '1985-05-15',
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson('/admin/contacts/persons/search?'.http_build_query([
            'search'        => 'John',
            'lead_id'       => $lead->id,
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
            'phones'        => [['value' => '123456789', 'label' => 'Mobile']],
            'date_of_birth' => '1985-05-15',
        ]));

    $response->assertOk();
    $data = $response->json();
    expect($data['data'])->toBeArray();

    // Should find exact match with score 100
    $exactMatch = collect($data['data'])->firstWhere('id', $exactMatchPerson->id);
    expect($exactMatch)->not->toBeNull();
    expect($exactMatch['score'])->toBe(100.0);
});
