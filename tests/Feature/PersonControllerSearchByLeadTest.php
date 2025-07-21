<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->personRepository = app(PersonRepository::class);
    $this->attributeRepository = app(AttributeRepository::class);
    $this->attributeValueRepository = app(AttributeValueRepository::class);
    $this->controller = new PersonController($this->personRepository, app(LeadRepository::class), $this->attributeRepository);

    Person::query()->delete();
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
    array $attributes = []
) {
    $person = Person::factory()->create([
        'emails'     => $emails,
        'first_name' => $firstName,
        'last_name'  => $lastName,
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

    // Create a person with different first name
    $nonMatchingPerson = createPersonWithAttributes($this->attributeValueRepository,
        'John',
        'Doe',
        [['value' => 'jane@example.com', 'label' => 'work']],
        [
            'contact_numbers'                      => [['value' => '0612345678', 'label' => 'mobile']],
        ]);

    // Call the method
    $result = $this->controller->searchByLead($lead);

    // Assert it finds both persons but orders them correctly by score
    expect($result)->toBeInstanceOf(JsonResource::class)
        ->and($result->collection)->toHaveCount(2)
        ->and($result->collection->first()->id)->toBe($matchingPerson->id); // Better match should be first
});
//
// test('finds exact email match', function () {
//    // Create a lead
//    $lead = Lead::factory()->create([
//        'first_name' => 'John',
//        'last_name'  => 'Doe',
//        'email'      => 'john@example.com',
//        'phone'      => '0612345678',
//    ]);
//
//    // Create a person with exact email match
//    $matchingPerson = Person::factory()->create([
//        'first_name'      => 'Jane',
//        'last_name'       => 'Smith',
//        'emails'          => [['value' => 'john@example.com', 'label' => 'work']],
//        'contact_numbers' => [['value' => '0687654321', 'label' => 'mobile']],
//    ]);
//
//    // Create a person with different email
//    $nonMatchingPerson = Person::factory()->create([
//        'first_name'      => 'John',
//        'last_name'       => 'Doe',
//        'emails'          => [['value' => 'jane@example.com', 'label' => 'work']],
//        'contact_numbers' => [['value' => '0612345678', 'label' => 'mobile']],
//    ]);
//
//    // Call the method
//    $result = $this->controller->searchByLead($lead);
//
//    // Assert it finds the matching person
//    expect($result)->toBeInstanceOf(JsonResource::class)
//        ->and($result->collection)->toHaveCount(1)
//        ->and($result->collection->first()->id)->toBe($matchingPerson->id);
// });
//
// test('finds exact phone match', function () {
//    // Create a lead
//    $lead = Lead::factory()->create([
//        'first_name' => 'John',
//        'last_name'  => 'Doe',
//        'email'      => 'john@example.com',
//        'phone'      => '0612345678',
//    ]);
//
//    // Create a person with exact phone match
//    $matchingPerson = Person::factory()->create([
//        'first_name'      => 'Jane',
//        'last_name'       => 'Smith',
//        'emails'          => [['value' => 'jane@example.com', 'label' => 'work']],
//        'contact_numbers' => [['value' => '0612345678', 'label' => 'mobile']],
//    ]);
//
//    // Create a person with different phone
//    $nonMatchingPerson = Person::factory()->create([
//        'first_name'      => 'John',
//        'last_name'       => 'Doe',
//        'emails'          => [['value' => 'john@example.com', 'label' => 'work']],
//        'contact_numbers' => [['value' => '0687654321', 'label' => 'mobile']],
//    ]);
//
//    // Call the method
//    $result = $this->controller->searchByLead($lead);
//
//    // Assert it finds the matching person
//    expect($result)->toBeInstanceOf(JsonResource::class)
//        ->and($result->collection)->toHaveCount(1)
//        ->and($result->collection->first()->id)->toBe($matchingPerson->id);
// });
//
// test('finds partial name match', function () {
//    // Create a lead
//    $lead = Lead::factory()->create([
//        'first_name' => 'John',
//        'last_name'  => 'Doe',
//        'email'      => 'john@example.com',
//        'phone'      => '0612345678',
//    ]);
//
//    // Create a person with partial name match
//    $matchingPerson = Person::factory()->create([
//        'first_name'      => 'Johnny',
//        'last_name'       => 'Smith',
//        'emails'          => [['value' => 'johnny@example.com', 'label' => 'work']],
//        'contact_numbers' => [['value' => '0687654321', 'label' => 'mobile']],
//    ]);
//
//    // Create a person with no name match
//    $nonMatchingPerson = Person::factory()->create([
//        'first_name'      => 'Jane',
//        'last_name'       => 'Doe',
//        'emails'          => [['value' => 'jane@example.com', 'label' => 'work']],
//        'contact_numbers' => [['value' => '0612345678', 'label' => 'mobile']],
//    ]);
//
//    // Call the method
//    $result = $this->controller->searchByLead($lead);
//
//    // Assert it finds the matching person
//    expect($result)->toBeInstanceOf(JsonResource::class)
//        ->and($result->collection)->toHaveCount(1)
//        ->and($result->collection->first()->id)->toBe($matchingPerson->id);
// });
//
// test('sorts by relevance score', function () {
//    // Create a lead
//    $lead = Lead::factory()->create([
//        'first_name' => 'John',
//        'last_name'  => 'Doe',
//        'email'      => 'john@example.com',
//        'phone'      => '0612345678',
//    ]);
//
//    // Create a person with exact first name match (score: 10)
//    $exactMatchPerson = Person::factory()->create([
//        'first_name'      => 'John',
//        'last_name'       => 'Smith',
//        'emails'          => [['value' => 'john.smith@example.com', 'label' => 'work']],
//        'contact_numbers' => [['value' => '0687654321', 'label' => 'mobile']],
//    ]);
//
//    // Create a person with partial name match (score: 5)
//    $partialMatchPerson = Person::factory()->create([
//        'first_name'      => 'Johnny',
//        'last_name'       => 'Doe',
//        'emails'          => [['value' => 'johnny@example.com', 'label' => 'work']],
//        'contact_numbers' => [['value' => '0687654321', 'label' => 'mobile']],
//    ]);
//
//    // Create a person with no match (score: 0)
//    $noMatchPerson = Person::factory()->create([
//        'first_name'      => 'Jane',
//        'last_name'       => 'Smith',
//        'emails'          => [['value' => 'jane@example.com', 'label' => 'work']],
//        'contact_numbers' => [['value' => '0687654321', 'label' => 'mobile']],
//    ]);
//
//    // Call the method
//    $result = $this->controller->searchByLead($lead);
//
//    // Assert it returns results sorted by relevance
//    expect($result)->toBeInstanceOf(JsonResource::class)
//        ->and($result->collection)->toHaveCount(2);
//
//    // First result should be the exact match
//    expect($result->collection->first()->id)->toBe($exactMatchPerson->id);
//
//    // Second result should be the partial match
//    expect($result->collection->get(1)->id)->toBe($partialMatchPerson->id);
// });
//
// test('limits results to top 10', function () {
//    // Create a lead
//    $lead = Lead::factory()->create([
//        'first_name' => 'John',
//        'last_name'  => 'Doe',
//        'email'      => 'john@example.com',
//        'phone'      => '0612345678',
//    ]);
//
//    // Create 15 persons with matching first name
//    for ($i = 1; $i <= 15; $i++) {
//        Person::factory()->create([
//            'first_name'      => 'John',
//            'last_name'       => "Person{$i}",
//            'emails'          => [['value' => "john{$i}@example.com", 'label' => 'work']],
//            'contact_numbers' => [['value' => "061234567{$i}", 'label' => 'mobile']],
//        ]);
//    }
//
//    // Call the method
//    $result = $this->controller->searchByLead($lead);
//
//    // Assert it limits results to 10
//    expect($result)->toBeInstanceOf(JsonResource::class)
//        ->and($result->collection)->toHaveCount(10);
// });
//
// test('handles case insensitive matching', function () {
//    // Create a lead with lowercase
//    $lead = Lead::factory()->create([
//        'first_name' => 'john',
//        'last_name'  => 'doe',
//        'email'      => 'JOHN@EXAMPLE.COM',
//        'phone'      => '0612345678',
//    ]);
//
//    // Create a person with uppercase
//    $matchingPerson = Person::factory()->create([
//        'first_name'      => 'JOHN',
//        'last_name'       => 'DOE',
//        'emails'          => [['value' => 'john@example.com', 'label' => 'work']],
//        'contact_numbers' => [['value' => '0612345678', 'label' => 'mobile']],
//    ]);
//
//    // Call the method
//    $result = $this->controller->searchByLead($lead);
//
//    // Assert it finds the matching person despite case differences
//    expect($result)->toBeInstanceOf(JsonResource::class)
//        ->and($result->collection)->toHaveCount(1)
//        ->and($result->collection->first()->id)->toBe($matchingPerson->id);
// });
//
// test('handles empty lead data', function () {
//    // Create a lead with minimal data
//    $lead = Lead::factory()->create([
//        'first_name' => null,
//        'last_name'  => null,
//        'email'      => null,
//        'phone'      => null,
//    ]);
//
//    // Create some persons
//    Person::factory()->count(3)->create();
//
//    // Call the method
//    $result = $this->controller->searchByLead($lead);
//
//    // Assert it returns empty collection when no search terms
//    expect($result)->toBeInstanceOf(JsonResource::class)
//        ->and($result->collection)->toHaveCount(0);
// });

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
