<?php

use App\Models\Address;
use App\Enums\ContactLabel;
use Database\Seeders\TestSeeder;
use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\Admin\Http\Controllers\Contact\Persons\PersonController;
use Webkul\Admin\Http\Resources\PersonResource;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->personRepository = app(PersonRepository::class);
    $this->attributeRepository = app(AttributeRepository::class);
    $this->attributeValueRepository = app(AttributeValueRepository::class);
    $this->controller = new PersonController($this->personRepository, app(LeadRepository::class), $this->attributeRepository);

    Person::query()->delete();

    // Create and authenticate a user
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// Voeg deze helper toe:
function createLeadWithAttributes(
    AttributeValueRepository $attributeValueRepository,
    string $firstName = 'John',
    string $lastName = 'Doe',
    array $attributes = []

) {
    $pipeline = Pipeline::first();
    $stage = Stage::first();

    if (! $pipeline || ! $stage) {
        throw new Exception('Pipeline or Stage not found. Make sure they are created in beforeEach.');
    }

    $lead = Lead::factory()->create([
        'first_name'             => $firstName,
        'last_name'              => $lastName,
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
    ]);
    $attributeValueRepository->save(array_merge($attributes, [
        'entity_id'   => $lead->id,
        'entity_type' => 'leads',
    ]));

    return $lead;
}

function createPersonWithAttributes(
    AttributeValueRepository $attributeValueRepository,
    string $firstName,
    string $lastName,
    array $emails,
    array $attributes = [],
    string $marriedName = '',
) {
    $person = Person::factory()->create([
        'emails'       => $emails,
        'first_name'   => $firstName,
        'last_name'    => $lastName,
        'married_name' => $marriedName,
    ]);
    $attributeValueRepository->save(array_merge($attributes, [
        'entity_id'   => $person->id,
        'entity_type' => 'persons',
    ]));

    return $person;
}

test('returns empty collection when no persons exist', function () {
    $lead = createLeadWithAttributes(
        $this->attributeValueRepository,
        'John',
        'Doe',
        [
            'email'                             => 'john@example.com',
            'phone'                             => '+31612345678',
        ]);

    // Call the method
    $result = $this->controller->searchByLead($lead);

    // Assert it returns an empty collection
    expect($result)->toBeInstanceOf(JsonResource::class)
        ->and($result->collection)->toHaveCount(0);
});

test('finds exact first name match', function () {
    $totalPersons = Person::count();
    $this->assertTrue($totalPersons === 0, 'No persons should exist before the test, found: '.$totalPersons);

    $lead = createLeadWithAttributes(
        $this->attributeValueRepository,
        'John',
        'Smith',
        [
            'email'                             => 'john@example.com',
            'phone'                             => '0612345678',
        ]);

    $this->assertNotNull($lead->first_name);

    // Create a person with exact first name match
    $matchingPerson = createPersonWithAttributes($this->attributeValueRepository,
        'John',
        'Smith',
        [['value' => 'john.smith@example.com', 'label' => ContactLabel::Eigen->value]],
        [
            'phones'                      => [['value' => '0687654321', 'label' => ContactLabel::Relatie->value]],
        ]);

    // must initial filter on married name of lastname
    $onlyNameMatchingPerson = createPersonWithAttributes($this->attributeValueRepository,
        'John',
        'Smith',
        [],
        [],
        'Smith'
    );

    // Call the method
    $result = $this->controller->searchByLead($lead);

    // Assert it finds both persons but orders them correctly by score
    expect($result)->toBeInstanceOf(JsonResource::class)
        ->and($result->collection)->toHaveCount(2)
        ->and($result->collection->first()->id)->toBe($matchingPerson->id); // Better match should be first
});

test('finds exact email match', function () {
    $lead = createLeadWithAttributes(
        $this->attributeValueRepository,
        'John',
        'Doe',
        [
            'email'                             => 'john@example.com',
            'phone'                             => '0612345678',
        ]);

    $noNameMatchingPerson = Person::factory()->create([
        'first_name'      => 'Jane',
        'last_name'       => 'Smith',
        'emails'          => [['value' => 'john2@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0687654321', 'label' => ContactLabel::Relatie->value]],
    ]);

    // Create a person with exact email match
    $matchingPerson = Person::factory()->create([
        'first_name'      => 'John',
        'last_name'       => 'Doe',
        'emails'          => [['value' => 'john@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0687654321', 'label' => ContactLabel::Relatie->value]],
    ]);

    // Create a person with different email
    $matchWithDifferentEmail = Person::factory()->create([
        'first_name'      => 'John',
        'last_name'       => 'Doe',
        'emails'          => [['value' => 'jane@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
    ]);

    // Call the method
    $result = $this->controller->searchByLead($lead);

    // Assert it finds the matching person
    expect($result)->toBeInstanceOf(JsonResource::class)
        ->and($result->collection)->toHaveCount(2)
        ->and($result->collection->first()->id)->toBe($matchingPerson->id);
});

test('returns results with match scores and sorts by score', function () {
    // Create a lead with all fields directly on the model
    $pipeline = Pipeline::first();
    $stage = Stage::first();

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith',
        'married_name'           => 'Johnson',
        'emails'                 => [['value' => 'john.smith@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'                 => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
    ]);

    // Create person with high match score (exact name and email and phone match)
    $highMatchPerson = Person::factory()->create([
        'first_name'      => 'John',
        'last_name'       => 'Smith',
        'married_name'    => 'Johnson',
        'emails'          => [['value' => 'john.smith@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
    ]);

    // Create person with medium match score (some name fields missing, different email/phone)
    $mediumMatchPerson = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Smith',
        // No married_name - this should result in lower score than highMatchPerson
        'emails'          => [['value' => 'different@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0687654321', 'label' => ContactLabel::Relatie->value]],
    ]);

    // Create person with low match score (first name only)
    $lowMatchPerson = Person::factory()->create([
        'first_name'      => 'John',
        'last_name'       => 'Doe',
        'emails'          => [['value' => 'john.doe@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0698765432', 'label' => ContactLabel::Relatie->value]],
    ]);

    $onlyNameMatch = Person::factory()
        ->withOrganisation('Familie Smith')
        ->create([
            'first_name'    => 'John',
            'last_name'     => 'Smith',
            'date_of_birth' => '1990-01-01',
        ]);

    // Call the method
    $result = $this->controller->searchByLead($lead);

    // Assert results are returned with scores
    expect($result)->toBeInstanceOf(JsonResource::class);

    $collection = $result->collection;

    // expect no result for John Doe
    expect($collection)->toHaveCount(3);

    // Assert all results have match scores
    foreach ($collection as $person) {
        expect($person->match_score)->toBeGreaterThan(0)
            ->and($person->match_score_percentage)->toBeGreaterThan(0);
    }

    // Assert results are sorted by score (highest first)
    $scores = $collection->pluck('match_score')->toArray();
    $sortedScores = collect($scores)->sortDesc()->values()->toArray();
    expect($scores)->toBe($sortedScores)
        ->and($collection->first()->id)->toBe($highMatchPerson->id);

    // Assert the high match person has the highest score
    // Use a small delta to handle floating point precision issues
    $firstScore = round($collection->first()->match_score, 2);
    $secondScore = round($collection->get(1)->match_score, 2);
    $x = $collection->filter(fn (PersonResource $p) => $p->organization?->name == 'Familie Smith');
    $this->assertTrue(! empty($x));
    $onlyNameMatch = round($x->first()->match_score, 2);
    expect($firstScore)->toBeGreaterThan($secondScore);
    $this->assertEquals(73, $onlyNameMatch);
});

test('validates email and phone array structure when creating person', function () {
    // Test case 1: Valid email and phone structure should pass
    $validData = [
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [
            ['value' => 'john@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'phones' => [
            ['value' => '+31612345678', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'entity_type' => 'persons',
    ];

    $response = $this->postJson('/admin/contacts/persons/create', $validData);
    $response->assertStatus(302);

    // Test case 2: Email without label should get default label work
    $invalidEmailData = [
        'first_name' => 'Jane',
        'last_name'  => 'Smith',
        'emails'     => [
            ['value' => 'jane@example.com', 'is_default' => true], // Missing label
        ],
        'entity_type' => 'persons',
    ];

    $response = $this->postJson('/admin/contacts/persons/create', $invalidEmailData);
    $response->assertStatus(302);

    // Test case 3: Phone without label should get default label work
    $invalidPhoneData = [
        'first_name' => 'Bob',
        'last_name'  => 'Johnson',
        'phones'     => [
            ['value' => '+31687654321', 'is_default' => true], // Missing label
        ],
        'entity_type' => 'persons',
    ];

    $response = $this->postJson('/admin/contacts/persons/create', $invalidPhoneData);
    $response->assertStatus(302);

    //    todo later
    //    // Test case 4: Invalid email label should fail
    //    $invalidLabelData = [
    //        'first_name' => 'Alice',
    //        'last_name' => 'Brown',
    //        'emails' => [
    //            ['value' => 'alice@example.com', 'label' => 'invalid_label', 'is_default' => true]
    //        ],
    //        'entity_type' => 'persons'
    //    ];
    //
    //    $response = $this->postJson('/admin/contacts/persons/create', $invalidLabelData);
    //    $response->assertStatus(422);
    //    $response->assertJsonValidationErrors(['emails']);

    // Test case 5: Empty values with labels should pass (allows for empty contact fields)
    $emptyValuesData = [
        'first_name' => 'Charlie',
        'last_name'  => 'Wilson',
        'emails'     => [
            ['value' => '', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'phones' => [
            ['value' => '', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'entity_type' => 'persons',
    ];

    $this->postJson('/admin/contacts/persons/create', $emptyValuesData)->assertStatus(302);
});

test('match algorithm includes date of birth and address in scoring', function () {
    // Create a lead with all fields including date of birth and address
    $pipeline = Pipeline::first();
    $stage = Stage::first();

    $lead = Lead::factory()->create([
        'first_name'             => 'Alice',
        'last_name'              => 'Johnson',
        'emails'                 => [['value' => 'alice.johnson@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'                 => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
        'date_of_birth'          => '1985-03-15',
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
    ]);

    // Create address for the lead
    Address::create([
        'lead_id'      => $lead->id,
        'street'       => 'Hoofdstraat',
        'house_number' => '123',
        'city'         => 'Amsterdam',
        'postal_code'  => '1012 AB',
        'country'      => 'Netherlands',
    ]);

    // Create person with perfect match (all fields including date of birth and address)
    $perfectMatchPerson = Person::factory()->create([
        'first_name'      => 'Alice',
        'last_name'       => 'Johnson',
        'emails'          => [['value' => 'alice.johnson@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
        'date_of_birth'   => '1985-03-15',
    ]);

    // Create address for perfect match person
    Address::create([
        'person_id'    => $perfectMatchPerson->id,
        'street'       => 'Hoofdstraat',
        'house_number' => '123',
        'city'         => 'Amsterdam',
        'postal_code'  => '1012 AB',
        'country'      => 'Netherlands',
    ]);

    // Create person without date of birth and address (should have lower score)
    $partialMatchPerson = Person::factory()->create([
        'first_name'      => 'Alice',
        'last_name'       => 'Johnson',
        'emails'          => [['value' => 'alice.johnson@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
        // No date_of_birth and no address
    ]);

    // Create person with different date of birth and address (should have lower score)
    $differentDataPerson = Person::factory()->create([
        'first_name'      => 'Alice',
        'last_name'       => 'Johnson',
        'emails'          => [['value' => 'alice.johnson@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
        'date_of_birth'   => '1990-06-20', // Different date
    ]);

    // Create address for different data person
    Address::create([
        'person_id'    => $differentDataPerson->id,
        'street'       => 'Kerkstraat',     // Different street
        'house_number' => '456',            // Different house number
        'city'         => 'Utrecht',        // Different city
        'postal_code'  => '3511 AB',        // Different postal code
        'country'      => 'Netherlands',    // Same country
    ]);

    // Load the address relationships
    $lead->load('address');
    $perfectMatchPerson->load('address');
    $differentDataPerson->load('address');

    // Call the method
    $result = $this->controller->searchByLead($lead);

    // Assert results are returned
    expect($result)->toBeInstanceOf(JsonResource::class);
    $collection = $result->collection;
    expect($collection)->toHaveCount(3);

    // Assert results are sorted by score (highest first)
    $scores = $collection->pluck('match_score')->toArray();
    $sortedScores = collect($scores)->sortDesc()->values()->toArray();
    expect($scores)->toBe($sortedScores);

    // The perfect match person should have the highest score
    expect($collection->first()->id)->toBe($perfectMatchPerson->id);

    // Get the actual scores for comparison
    $perfectScore = round($collection->first()->match_score, 2);
    $partialScore = round($collection->get(1)->match_score, 2);
    $differentScore = round($collection->get(2)->match_score, 2);

    // Perfect match should have higher score than partial match
    expect($perfectScore)->toBeGreaterThan($partialScore)
        ->and($partialScore)->toBeGreaterThan($differentScore)
        ->and($perfectScore)->toBeGreaterThan(80)
        ->and($partialScore)->toBeLessThan($perfectScore)
        ->and($partialScore)->toBeGreaterThan(75)
        ->and($differentScore)->toBeLessThan($partialScore);

    // Partial match should have higher score than different data match

    // Verify that the perfect match has a very high score
    // With 85% for names + date_of_birth, 5% for email, 5% for phone, 5% for address
    // Should be high due to all matches

    // Verify that partial match (missing date_of_birth and address) has lower score
    // Should have 85% * (name_match_ratio) + 5% + 5% + 0% for address
    // Should still be high due to name, email, phone match

    // Verify that different data match has even lower score
});

test('address matching works with partial postal code matches', function () {
    // Create a lead with Dutch postal code format
    $pipeline = Pipeline::first();
    $stage = Stage::first();

    $lead = Lead::factory()->create([
        'first_name'             => 'Bob',
        'last_name'              => 'Wilson',
        'emails'                 => [['value' => 'bob.wilson@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'                 => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
    ]);

    // Create address for the lead
    Address::create([
        'lead_id'      => $lead->id,
        'street'       => 'Damrak',
        'house_number' => '1',
        'city'         => 'Amsterdam',
        'postal_code'  => '1012JS', // Without space
        'country'      => 'Netherlands',
    ]);

    // Create person with exact address match
    $exactMatchPerson = Person::factory()->create([
        'first_name'      => 'Bob',
        'last_name'       => 'Wilson',
        'emails'          => [['value' => 'bob.wilson@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
    ]);

    Address::create([
        'person_id'    => $exactMatchPerson->id,
        'street'       => 'Damrak',
        'house_number' => '1',
        'city'         => 'Amsterdam',
        'postal_code'  => '1012JS',
        'country'      => 'Netherlands',
    ]);

    // Create person with partial postal code match (with space)
    $partialMatchPerson = Person::factory()->create([
        'first_name'      => 'Bob',
        'last_name'       => 'Wilson',
        'emails'          => [['value' => 'bob.wilson@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
    ]);

    Address::create([
        'person_id'    => $partialMatchPerson->id,
        'street'       => 'Damrak',
        'house_number' => '1',
        'city'         => 'Amsterdam',
        'postal_code'  => '1012 JS', // With space - should still partially match
        'country'      => 'Netherlands',
    ]);

    // Create person with different address
    $differentAddressPerson = Person::factory()->create([
        'first_name'      => 'Bob',
        'last_name'       => 'Wilson',
        'emails'          => [['value' => 'bob.wilson@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
    ]);

    Address::create([
        'person_id'    => $differentAddressPerson->id,
        'street'       => 'Kalverstraat',
        'house_number' => '123',
        'city'         => 'Utrecht',
        'postal_code'  => '3511 AB',
        'country'      => 'Netherlands',
    ]);

    // Load address relationships
    $lead->load('address');
    $exactMatchPerson->load('address');
    $partialMatchPerson->load('address');
    $differentAddressPerson->load('address');

    // Call the method
    $result = $this->controller->searchByLead($lead);

    // Assert results are returned
    expect($result)->toBeInstanceOf(JsonResource::class);
    $collection = $result->collection;
    expect($collection)->toHaveCount(3);

    // Get the scores
    $exactScore = round($collection->firstWhere('id', $exactMatchPerson->id)->match_score, 2);
    $partialScore = round($collection->firstWhere('id', $partialMatchPerson->id)->match_score, 2);
    $differentScore = round($collection->firstWhere('id', $differentAddressPerson->id)->match_score, 2);

    // Exact match should be at least as high as partial (equal after normalization)
    expect($exactScore)->toBeGreaterThanOrEqual($partialScore)
        ->and($partialScore)->toBeGreaterThan($differentScore);

    // Partial match should have higher score than different address

    // The difference should be relatively small (only 5% weight for address)
    $scoreDifference = $exactScore - $partialScore;
    expect($scoreDifference)->toBeLessThan(5); // Should be less than 5% difference
});

test('date of birth matching affects name field scoring', function () {
    // Create a lead with date of birth
    $pipeline = Pipeline::first();
    $stage = Stage::first();

    $lead = Lead::factory()->create([
        'first_name'             => 'Charlie',
        'last_name'              => 'Brown',
        'emails'                 => [['value' => 'charlie.brown@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'                 => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
        'date_of_birth'          => '1980-12-25',
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
    ]);

    // Create person with matching date of birth
    $matchingDatePerson = Person::factory()->create([
        'first_name'      => 'Charlie',
        'last_name'       => 'Brown',
        'emails'          => [['value' => 'charlie.brown@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
        'date_of_birth'   => '1980-12-25', // Exact match
    ]);

    // Create person with different date of birth
    $differentDatePerson = Person::factory()->create([
        'first_name'      => 'Charlie',
        'last_name'       => 'Brown',
        'emails'          => [['value' => 'charlie.brown@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
        'date_of_birth'   => '1985-06-15', // Different date
    ]);

    // Create person without date of birth
    $noDatePerson = Person::factory()->create([
        'first_name'      => 'Charlie',
        'last_name'       => 'Brown',
        'emails'          => [['value' => 'charlie.brown@example.com', 'label' => ContactLabel::Eigen->value]],
        'phones'          => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value]],
        // No date_of_birth
    ]);

    // Call the method
    $result = $this->controller->searchByLead($lead);

    // Assert results are returned
    expect($result)->toBeInstanceOf(JsonResource::class);
    $collection = $result->collection;
    expect($collection)->toHaveCount(3);

    // Get the scores
    $matchingDateScore = round($collection->firstWhere('id', $matchingDatePerson->id)->match_score, 2);
    $differentDateScore = round($collection->firstWhere('id', $differentDatePerson->id)->match_score, 2);
    $noDateScore = round($collection->firstWhere('id', $noDatePerson->id)->match_score, 2);

    // Person with matching date should have highest score
    expect($matchingDateScore)->toBeGreaterThanOrEqual($differentDateScore);
    expect($matchingDateScore)->toBeGreaterThanOrEqual($noDateScore);

    // All persons should have reasonably high scores due to name, email, phone matches
    expect($matchingDateScore)->toBeGreaterThan(70);
    expect($differentDateScore)->toBeGreaterThan(70);
    expect($noDateScore)->toBeGreaterThan(70);

    // The scores should reflect the date_of_birth matching impact
    $scoreDifference = $matchingDateScore - $differentDateScore;
    expect($scoreDifference)->toBeGreaterThanOrEqual(0);
});
