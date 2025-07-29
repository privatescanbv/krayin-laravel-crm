<?php

use App\Models\Address;
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
function createPipelineDataEditWithLead(): array
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

test('can access edit with lead page with all fields', function () {
    $data = createPipelineDataEditWithLead();

    $person = Person::factory()->create([
        'salutation'          => 'Mr.',
        'first_name'          => 'John',
        'last_name'           => 'Doe',
        'lastname_prefix'     => 'van',
        'married_name'        => 'Johnson',
        'married_name_prefix' => 'de',
        'initials'            => 'J.D.',
        'emails'              => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'              => [['value' => '123456789', 'label' => 'Mobile']],
        'date_of_birth'       => '1990-01-01',
        'gender'              => 'male',
        'user_id'             => test()->user->id,
    ]);

    // Create address for person
    Address::create([
        'person_id'           => $person->id,
        'street'              => 'Main Street',
        'house_number'        => '123',
        'house_number_suffix' => 'A',
        'postal_code'         => '1234AB',
        'city'                => 'Amsterdam',
        'state'               => 'North Holland',
        'country'             => 'Netherlands',
    ]);

    $lead = Lead::factory()->create([
        'title'                  => 'Lead Title',
        'salutation'             => 'Dr.',
        'first_name'             => 'John',
        'last_name'              => 'Smith',
        'lastname_prefix'        => 'de',
        'married_name'           => 'Williams',
        'married_name_prefix'    => 'van',
        'initials'               => 'J.S.',
        'emails'                 => [['value' => 'john.smith@example.com', 'label' => 'Work']],
        'phones'                 => [['value' => '987654321', 'label' => 'Mobile']],
        'date_of_birth'          => '1985-05-15',
        'gender'                 => 'male',
        'lead_value'             => 5000.00,
        'description'            => 'Test lead description',
        'lost_reason'            => null,
        'expected_close_date'    => '2024-12-31',
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    // Create address for lead
    Address::create([
        'lead_id'             => $lead->id,
        'street'              => 'Second Street',
        'house_number'        => '456',
        'house_number_suffix' => 'B',
        'postal_code'         => '5678CD',
        'city'                => 'Rotterdam',
        'state'               => 'South Holland',
        'country'             => 'Netherlands',
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->get(route('admin.contacts.persons.edit_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]))->assertOk();
    $response->assertViewIs('admin::contacts.persons.edit-with-lead');
    $response->assertViewHas('person', $person);
    $response->assertViewHas('lead', $lead);
    $response->assertViewHas('fieldDifferences');

    // Check that all different fields are shown
    $response->assertSee('Dr.'); // Different salutation
    $response->assertSee('Smith'); // Different last name
    $response->assertSee('de'); // Different lastname_prefix
    $response->assertSee('Williams'); // Different married_name
    $response->assertSee('van'); // Different married_name_prefix
    $response->assertSee('J.S.'); // Different initials
    $response->assertSee('john.smith@example.com'); // Different email
    $response->assertSee('987654321'); // Different phone
    $response->assertSee('1985-05-15'); // Different birth date
    $response->assertSee('Second Street'); // Different address
    $response->assertSee('Rotterdam'); // Different city
});

test('shows no differences when person and lead have identical data', function () {
    $data = createPipelineDataEditWithLead();

    // Create person with minimal data to avoid factory defaults
    $person = new Person;
    $person->first_name = 'John';
    $person->last_name = 'Doe';
    $person->emails = [['value' => 'john@example.com', 'label' => 'Work']];
    $person->user_id = test()->user->id;
    $person->save();

    // Create lead with minimal data to avoid factory defaults
    $lead = new Lead;
    $lead->first_name = 'John';
    $lead->last_name = 'Doe';
    $lead->title = 'Lead for John Doe';
    $lead->emails = [['value' => 'john@example.com', 'label' => 'Work']];
    $lead->lead_pipeline_id = $data['pipelineId'];
    $lead->lead_pipeline_stage_id = $data['stageId'];
    $lead->user_id = test()->user->id;
    $lead->save();

    test()
        ->actingAs(test()->user, 'user')
        ->get(route('admin.contacts.persons.edit_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]))
        ->assertOk()
        ->assertSee('Geen verschillen gevonden');
});

test('can update person with lead address', function () {
    $data = createPipelineDataEditWithLead();

    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'user_id'    => test()->user->id,
    ]);

    // Create initial address for person
    Address::create([
        'person_id'    => $person->id,
        'street'       => 'Old Street',
        'house_number' => '111',
        'postal_code'  => '1111AA',
        'city'         => 'Old City',
        'country'      => 'Netherlands',
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith', // Different
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    // Create address for lead
    Address::create([
        'lead_id'             => $lead->id,
        'street'              => 'New Street',
        'house_number'        => '456',
        'house_number_suffix' => 'B',
        'postal_code'         => '5678CD',
        'city'                => 'New City',
        'state'               => 'New State',
        'country'             => 'Netherlands',
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->postJson(route('admin.contacts.persons.update_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]), [
            'person_updates' => [
                'last_name' => '1', // Update last name
                'address'   => '1', // Update address
            ],
            'lead_updates' => [
                'last_name' => 'Smith',
                'address'   => 'lead_address_data', // This will be ignored for address
            ],
        ]);

    $response->assertOk();
    $response->assertJson([
        'message'      => 'Person en lead succesvol bijgewerkt.',
        'redirect_url' => route('admin.contacts.persons.view', $person->id),
    ]);

    // Verify person was updated
    $person->refresh();
    expect($person->last_name)->toBe('Smith');

    // Verify address was copied from lead to person
    $personAddress = $person->address;
    expect($personAddress)->not->toBeNull()
        ->and($personAddress->street)->toBe('New Street')
        ->and($personAddress->house_number)->toBe('456')
        ->and($personAddress->house_number_suffix)->toBe('B')
        ->and($personAddress->postal_code)->toBe('5678CD')
        ->and($personAddress->city)->toBe('New City')
        ->and($personAddress->state)->toBe('New State')
        ->and($personAddress->country)->toBe('Netherlands')
        ->and($personAddress->person_id)->toBe($person->id)
        ->and($personAddress->lead_id)->toBeNull();
});

test('can update person with multiple lead fields including new fields', function () {
    $data = createPipelineDataEditWithLead();

    $person = Person::factory()->create([
        'salutation'          => 'Mr.',
        'first_name'          => 'John',
        'last_name'           => 'Doe',
        'lastname_prefix'     => 'van',
        'married_name'        => 'Johnson',
        'married_name_prefix' => 'de',
        'initials'            => 'J.D.',
        'emails'              => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'              => [['value' => '123456789', 'label' => 'Mobile']],
        'date_of_birth'       => '1990-01-01',
        'gender'              => 'male',
        'user_id'             => test()->user->id,
    ]);

    $lead = Lead::factory()->create([
        'title'                  => 'New Lead Title',
        'salutation'             => 'Dr.',
        'first_name'             => 'John',
        'last_name'              => 'Smith',
        'lastname_prefix'        => 'de',
        'married_name'           => 'Williams',
        'married_name_prefix'    => 'van',
        'initials'               => 'J.S.',
        'emails'                 => [['value' => 'john.smith@example.com', 'label' => 'Work']],
        'phones'                 => [['value' => '987654321', 'label' => 'Mobile']],
        'date_of_birth'          => '1985-05-15',
        'gender'                 => 'female',
        'lead_value'             => 7500.00,
        'description'            => 'Updated lead description',
        'lost_reason'            => 'Competition',
        'expected_close_date'    => '2024-06-30',
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->postJson(route('admin.contacts.persons.update_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]), [
            'person_updates' => [
                'salutation'          => '1',
                'last_name'           => '1',
                'lastname_prefix'     => '1',
                'married_name'        => '1',
                'married_name_prefix' => '1',
                'initials'            => '1',
                'emails'              => '1',
                'phones'              => '1',
                'date_of_birth'       => '1',
                'gender'              => '1',
            ],
            'lead_updates' => [
                'salutation'          => 'Dr.',
                'last_name'           => 'Smith',
                'lastname_prefix'     => 'de',
                'married_name'        => 'Williams',
                'married_name_prefix' => 'van',
                'initials'            => 'J.S.',
                'emails'              => 'john.smith@example.com',
                'phones'              => '987654321',
                'date_of_birth'       => '1985-05-15',
                'gender'              => 'female',
            ],
        ]);

    $response->assertOk();

    // Verify person was updated with all new values
    $person->refresh();
    expect($person->salutation)->toBe('Dr.');
    expect($person->last_name)->toBe('Smith');
    expect($person->lastname_prefix)->toBe('de');
    expect($person->married_name)->toBe('Williams');
    expect($person->married_name_prefix)->toBe('van');
    expect($person->initials)->toBe('J.S.');
    expect($person->emails[0]['value'])->toBe('john.smith@example.com');
    expect($person->phones[0]['value'])->toBe('987654321');
    expect($person->date_of_birth->format('Y-m-d'))->toBe('1985-05-15');
    expect($person->gender)->toBe('female');
});

test('address is replaced when person already has address', function () {
    $data = createPipelineDataEditWithLead();

    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'user_id'    => test()->user->id,
    ]);

    // Create existing address for person
    $oldAddress = Address::create([
        'person_id'    => $person->id,
        'street'       => 'Old Street',
        'house_number' => '111',
        'postal_code'  => '1111AA',
        'city'         => 'Old City',
        'country'      => 'Netherlands',
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    // Create address for lead
    Address::create([
        'lead_id'      => $lead->id,
        'street'       => 'New Street',
        'house_number' => '456',
        'postal_code'  => '5678CD',
        'city'         => 'New City',
        'country'      => 'Netherlands',
    ]);

    // Update person with lead address
    test()
        ->actingAs(test()->user, 'user')
        ->postJson(route('admin.contacts.persons.update_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]), [
            'person_updates' => [
                'address' => '1',
            ],
            'lead_updates' => [
                'address' => 'ignored',
            ],
        ]);

    // Verify old address was deleted
    expect(Address::find($oldAddress->id))->toBeNull();

    // Verify new address was created
    $person->refresh();
    $newAddress = $person->address;
    expect($newAddress)->not->toBeNull()
        ->and($newAddress->id)->not->toBe($oldAddress->id)
        ->and($newAddress->street)->toBe('New Street')
        ->and($newAddress->house_number)->toBe('456')
        ->and($newAddress->postal_code)->toBe('5678CD')
        ->and($newAddress->city)->toBe('New City')
        ->and($newAddress->person_id)->toBe($person->id);
});

test('does not update address when address field is not selected', function () {
    $data = createPipelineDataEditWithLead();

    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'user_id'    => test()->user->id,
    ]);

    // Create existing address for person
    Address::create([
        'person_id'    => $person->id,
        'street'       => 'Original Street',
        'house_number' => '111',
        'postal_code'  => '1111AA',
        'city'         => 'Original City',
        'country'      => 'Netherlands',
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith', // Different
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
    ]);

    // Create different address for lead
    Address::create([
        'lead_id'      => $lead->id,
        'street'       => 'Lead Street',
        'house_number' => '456',
        'postal_code'  => '5678CD',
        'city'         => 'Lead City',
        'country'      => 'Netherlands',
    ]);

    // Update only last name, not address
    test()
        ->actingAs(test()->user, 'user')
        ->postJson(route('admin.contacts.persons.update_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]), [
            'person_updates' => [
                'last_name' => '1', // Only update last name
                // address is NOT included
            ],
            'lead_updates' => [
                'last_name' => 'Smith',
            ],
        ]);

    // Verify person's address remains unchanged
    $person->refresh();
    expect($person->last_name)->toBe('Smith'); // Last name updated

    $address = $person->address;
    expect($address)->not->toBeNull()
        ->and($address->street)->toBe('Original Street')
        ->and($address->city)->toBe('Original City');
    // Address unchanged
});
