<?php

use Illuminate\Support\Facades\DB;
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

    // Create a test user
    test()->user = User::factory()->create();

});

// Helper to get required pipeline/stage data and ensure authentication
function createPipelineData(): array
{
    // Re-authenticate to prevent race conditions
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
    $data = createPipelineData(); // Get pipeline data and ensure authentication
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john@example.com', 'label' => 'Work']],
        'user_id'    => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith',
        'emails'                 => [['value' => 'john.smith@example.com', 'label' => 'Work']],
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson(route('admin.contacts.persons.edit_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]))->assertOk();
    $response->assertViewIs('admin::contacts.persons.edit-with-lead');
    $response->assertViewHas('person', $person);
    $response->assertViewHas('lead', $lead);
});

test('shows field differences between person and lead', function () {
    test()->assertNotNull(test()->user, 'User must be authenticated for this test');
    $data = createPipelineData(); // Get pipeline data (auth is mocked)
    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'        => [['value' => '123456789', 'label' => 'Mobile']],
        'date_of_birth' => '1990-01-01',
        'user_id'       => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith', // Different last name
        'emails'                 => [['value' => 'john.smith@example.com', 'label' => 'Work']], // Different email
        'phones'                 => [['value' => '123456789', 'label' => 'Mobile']], // Same phone
        'date_of_birth'          => '1985-05-15', // Different birth date
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id, ]);

    $response = test()->get(route('admin.contacts.persons.edit_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]))->assertOk();

    // Check that differences are shown
    $response->assertSee('Smith'); // Lead's different last name
    $response->assertSee('john.smith@example.com'); // Lead's different email
    $response->assertSee('1985-05-15'); // Lead's different birth date

    // Should not show phone as it's the same
    $response->assertDontSee('Telefoonnummers'); // Should not appear in differences table
});

test('can update person with lead data', function () {
    $data = createPipelineData(); // Get pipeline data (auth is mocked)
    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
        'date_of_birth' => '1990-01-01',
        'user_id'       => test()->user->id, ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith',
        'emails'                 => [['value' => 'john.smith@example.com', 'label' => 'Work']],
        'date_of_birth'          => '1985-05-15',
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id, ]);

    $response = test()->postJson(route('admin.contacts.persons.update_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]), [
        'person_updates' => [
            'last_name'     => '1', // Update last name
            'date_of_birth' => '1', // Update birth date
        ],
        'lead_updates' => [
            'last_name'     => 'Smith',
            'emails'        => 'john.smith@example.com',
            'date_of_birth' => '1985-05-15',
        ],
    ])->assertOk();
    $response->assertJson([
        'message'      => 'Person en lead succesvol bijgewerkt.',
        'redirect_url' => route('admin.contacts.persons.view', $person->id),
    ]);

    // Verify person was updated
    $person->refresh();
    expect($person->last_name)->toBe('Smith')
        ->and($person->date_of_birth->format('Y-m-d'))->toBe('1985-05-15')
        ->and($person->emails[0]['value'])->toBe('john@example.com');
    // Email should not be updated as it wasn't checked
});

test('can update lead data during sync', function () {
    $data = createPipelineData(); // Get pipeline data (auth is mocked)
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'user_id'    => test()->user->id, ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith',
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id, ]);

    test()
        ->actingAs(test()->user, 'user')
        ->postJson(route('admin.contacts.persons.update_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]), [
            'person_updates' => [
                'last_name' => '1', // Update person with modified lead value
            ],
            'lead_updates' => [
                'last_name' => 'Johnson', // Modify lead value
            ],
        ])->assertOk();

    // Verify both were updated
    $person->refresh();
    $lead->refresh();

    expect($person->last_name)->toBe('Johnson')
        ->and($lead->last_name)->toBe('Johnson');
});

test('handles array fields correctly during sync', function () {
    $data = createPipelineData(); // Get pipeline data (auth is mocked)
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'     => [['value' => '123456789', 'label' => 'Mobile']],
        'user_id'    => test()->user->id, ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'emails'                 => [['value' => 'john.smith@example.com', 'label' => 'Work']],
        'phones'                 => [['value' => '987654321', 'label' => 'Mobile']],
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id, ]);

    test()->postJson(route('admin.contacts.persons.update_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]), [
        'person_updates' => [
            'emails' => '1',
            'phones' => '1',
        ],
        'lead_updates' => [
            'emails' => 'john.updated@example.com, john.second@example.com',
            'phones' => '111222333, 444555666',
        ],
    ])->assertOk();

    // Verify arrays were updated correctly
    $person->refresh();

    expect($person->emails)->toHaveCount(2)
        ->and($person->emails[0]['value'])->toBe('john.updated@example.com')
        ->and($person->emails[1]['value'])->toBe('john.second@example.com')
        ->and($person->phones)->toHaveCount(2)
        ->and($person->phones[0]['value'])->toBe('111222333')
        ->and($person->phones[1]['value'])->toBe('444555666');

});

test('returns validation error for invalid data', function () {
    $data = createPipelineData(); // Get pipeline data (auth is mocked)
    $person = Person::factory()->create([
        'user_id' => test()->user->id,
    ]);
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    // Test with invalid person ID
    $response = test()->post(route('admin.contacts.persons.update_with_lead', [
        'personId' => 99999,
        'leadId'   => $lead->id,
    ]), []);

    $response->assertStatus(404);
});

test('shows no differences message when records are identical', function () {
    $data = createPipelineData(); // Get pipeline data (auth is mocked)
    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
        'date_of_birth' => '1990-01-01',
        'user_id'       => test()->user->id, ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'emails'                 => [['value' => 'john@example.com', 'label' => 'Work']],
        'date_of_birth'          => '1990-01-01',
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id, ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson(route('admin.contacts.persons.edit_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]))->assertOk();
    $response->assertSee('Geen verschillen gevonden');
});

test('handles edge case with malformed birth dates', function () {
    $data = createPipelineData(); // Get pipeline data (auth is mocked)

    // Create person with potentially malformed date
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'user_id'    => test()->user->id,
    ]);

    // Manually set a malformed date in the database
    DB::table('persons')->where('id', $person->id)->update(['date_of_birth' => '0000-00-00']);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'date_of_birth'          => null,
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
        ]))->assertOk();

    // Should not show birth date differences when one is malformed and other is null
    $response->assertDontSee('Geboortedatum');
    $response->assertDontSee('0000-00-00');
    $response->assertDontSee('-0001-11-30');
});

test('handles date comparison with valid vs invalid dates', function () {
    $data = createPipelineData(); // Get pipeline data (auth is mocked)
    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'date_of_birth' => '1990-01-01', // Valid date
        'user_id'       => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'date_of_birth'          => null, // No date
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson(route('admin.contacts.persons.edit_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]))->assertOk();

    // Should show birth date difference when one has valid date and other is null
    $response->assertSee('Geboortedatum');
    $response->assertSee('1990-01-01');
});

test('validates required route parameters', function () {
    $data = createPipelineData(); // Get pipeline data (auth is mocked)
    // Test with missing person ID
    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson('/admin/contacts/persons/edit-with-lead//1');
    $response->assertStatus(404);

    // Test with missing lead ID
    $response = test()->getJson('/admin/contacts/persons/edit-with-lead/1/');
    $response->assertStatus(404);
});

test('handles empty form submission gracefully', function () {
    $data = createPipelineData(); // Get pipeline data (auth is mocked)
    $person = Person::factory()->create();
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id, ]);

    test()
        ->actingAs(test()->user, 'user')
        ->postJson(route('admin.contacts.persons.update_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]))->assertOk();
});

test('manual search returns match scores when lead_id provided', function () {
    $data = createPipelineData(); // Get pipeline data (auth is mocked)
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john@example.com', 'label' => 'Work']],
        'user_id'    => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith', // Different last name for partial match
        'emails'                 => [['value' => 'john@example.com', 'label' => 'Work']], // Same email
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id, ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson('/admin/contacts/persons/search?'.http_build_query([
            'query'   => 'John',
            'lead_id' => $lead->id,
        ]))->assertOk();

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();

    // Find our test person in the results
    $testPerson = collect($data)->firstWhere('id', $person->id);
    expect($testPerson)->not->toBeNull()
        ->and($testPerson['match_score_percentage'])->toBeGreaterThan(0);
});

test('manual search without lead_id returns regular results', function () {
    $data = createPipelineData(); // Get pipeline data (auth is mocked)
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'user_id'    => test()->user->id,
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->getJson('/admin/contacts/persons/search?'.http_build_query([
            'query' => 'John',
        ]))->assertOk();

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();

    // Results should not have match scores
    $testPerson = collect($data)->firstWhere('id', $person->id);
    expect($testPerson)->not->toBeNull()
        ->and($testPerson)->not->toHaveKey('match_score_percentage');
});
