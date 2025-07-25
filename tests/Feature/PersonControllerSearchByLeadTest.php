<?php

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
        'title'                  => 'Test Lead',
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
            'phone'                             => '0612345678',
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
        [['value' => 'john.smith@example.com', 'label' => 'work']],
        [
            'contact_numbers'                      => [['value' => '0687654321', 'label' => 'mobile']],
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
        'emails'          => [['value' => 'john2@example.com', 'label' => 'work']],
        'contact_numbers' => [['value' => '0687654321', 'label' => 'mobile']],
    ]);

    // Create a person with exact email match
    $matchingPerson = Person::factory()->create([
        'first_name'      => 'John',
        'last_name'       => 'Doe',
        'emails'          => [['value' => 'john@example.com', 'label' => 'work']],
        'contact_numbers' => [['value' => '0687654321', 'label' => 'mobile']],
    ]);

    // Create a person with different email
    $matchWithDifferentEmail = Person::factory()->create([
        'first_name'      => 'John',
        'last_name'       => 'Doe',
        'emails'          => [['value' => 'jane@example.com', 'label' => 'work']],
        'contact_numbers' => [['value' => '0612345678', 'label' => 'mobile']],
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
        'title'                  => 'Test Lead',
        'first_name'             => 'John',
        'last_name'              => 'Smith',
        'married_name'           => 'Johnson',
        'emails'                 => [['value' => 'john.smith@example.com', 'label' => 'work']],
        'phones'                 => [['value' => '0612345678', 'label' => 'mobile']],
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
    ]);

    // Create person with high match score (exact name and email and phone match)
    $highMatchPerson = Person::factory()->create([
        'first_name'      => 'John',
        'last_name'       => 'Smith',
        'married_name'    => 'Johnson',
        'emails'          => [['value' => 'john.smith@example.com', 'label' => 'work']],
        'contact_numbers' => [['value' => '0612345678', 'label' => 'mobile']],
    ]);

    // Create person with medium match score (some name fields missing, different email/phone)
    $mediumMatchPerson = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Smith',
        // No married_name - this should result in lower score than highMatchPerson
        'emails'          => [['value' => 'different@example.com', 'label' => 'work']],
        'contact_numbers' => [['value' => '0687654321', 'label' => 'mobile']],
    ]);

    // Create person with low match score (first name only)
    $lowMatchPerson = Person::factory()->create([
        'first_name'      => 'John',
        'last_name'       => 'Doe',
        'emails'          => [['value' => 'john.doe@example.com', 'label' => 'work']],
        'contact_numbers' => [['value' => '0698765432', 'label' => 'mobile']],
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
    expect($scores)->toBe($sortedScores);

    // Assert the high match person has the highest score
    expect($collection->first()->id)->toBe($highMatchPerson->id);
    // Use a small delta to handle floating point precision issues
    $firstScore = round($collection->first()->match_score, 2);
    $secondScore = round($collection->get(1)->match_score, 2);
    $x = $collection->filter(fn (PersonResource $p) => $p->organization?->name == 'Familie Smith');
    $this->assertTrue(! empty($x));
    $onlyNameMatch = round($x->first()->match_score, 2);
    expect($firstScore)->toBeGreaterThan($secondScore);
    $this->assertEquals($onlyNameMatch, 72);
});

test('validates email and phone array structure when creating person', function () {
    // Test case 1: Valid email and phone structure should pass
    $validData = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'emails' => [
            ['value' => 'john@example.com', 'label' => 'work', 'is_default' => true]
        ],
        'phones' => [
            ['value' => '+31612345678', 'label' => 'work', 'is_default' => true]
        ],
        'entity_type' => 'persons'
    ];

    $response = $this->postJson('/admin/contacts/persons/create', $validData);
    $response->assertStatus(200);

    // Test case 2: Email without label should fail
    $invalidEmailData = [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'emails' => [
            ['value' => 'jane@example.com', 'is_default' => true] // Missing label
        ],
        'entity_type' => 'persons'
    ];

    $response = $this->postJson('/admin/contacts/persons/create', $invalidEmailData);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['emails']);

    // Test case 3: Phone without label should fail
    $invalidPhoneData = [
        'first_name' => 'Bob',
        'last_name' => 'Johnson',
        'phones' => [
            ['value' => '+31687654321', 'is_default' => true] // Missing label
        ],
        'entity_type' => 'persons'
    ];

    $response = $this->postJson('/admin/contacts/persons/create', $invalidPhoneData);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['phones']);

    // Test case 4: Invalid email label should fail
    $invalidLabelData = [
        'first_name' => 'Alice',
        'last_name' => 'Brown',
        'emails' => [
            ['value' => 'alice@example.com', 'label' => 'invalid_label', 'is_default' => true]
        ],
        'entity_type' => 'persons'
    ];

    $response = $this->postJson('/admin/contacts/persons/create', $invalidLabelData);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['emails']);

    // Test case 5: Empty values with labels should pass (allows for empty contact fields)
    $emptyValuesData = [
        'first_name' => 'Charlie',
        'last_name' => 'Wilson',
        'emails' => [
            ['value' => '', 'label' => 'work', 'is_default' => true]
        ],
        'phones' => [
            ['value' => '', 'label' => 'work', 'is_default' => true]
        ],
        'entity_type' => 'persons'
    ];

    $response = $this->postJson('/admin/contacts/persons/create', $emptyValuesData);
    $response->assertStatus(200);
});
