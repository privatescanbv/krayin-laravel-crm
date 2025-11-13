<?php

use App\Enums\ContactLabel;
use Tests\Feature\Concerns\ControllerSearchTestHelpers;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

uses(ControllerSearchTestHelpers::class);

beforeEach(function () {
    $this->setUpSearchTest();
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
});

test('person search by name fields works', function () {
    $p = Person::factory()->create([
        'first_name' => 'Eva',
        'last_name'  => 'Kuijer',
        'user_id'    => $this->user->id,
    ]);

    $resp = $this->performSearch('admin.contacts.persons.search', [
        'search'       => 'name:Kuijer;',
        'searchFields' => 'first_name:like;last_name:like;married_name:like;',
    ]);

    $this->assertEntityFound($resp, $p->id);
});

test('person search by phone token works', function () {
    $p = Person::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => 'Tester',
        'phones'     => [
            ['value' => '0687654321', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'user_id'    => $this->user->id,
    ]);

    // Use the convenience token path ("phone:") to ensure normalization is exercised
    $resp = $this->performSearch('admin.contacts.persons.search', [
        'search' => 'phone:0687654321;',
    ]);

    $this->assertEntityFound($resp, $p->id);
});

test('person search by firstname and lastname tokens works', function () {
    $p = Person::factory()->create([
        'first_name' => 'EvaToken',
        'last_name'  => 'KuyperToken',
        'user_id'    => $this->user->id,
    ]);

    $resp = $this->performSearch('admin.contacts.persons.search', [
        'search' => 'firstname:EvaToken;lastname:KuyperToken;',
    ]);

    $this->assertEntityFound($resp, $p->id);
});

test('person search by lastname token also matches married_name', function () {
    $p = Person::factory()->create([
        'first_name'      => 'Anna',
        'last_name'       => 'Maas',
        'married_name'    => 'De Bruin',
        'user_id'         => $this->user->id,
    ]);

    // Should match on married_name using lastname: token
    $resp = $this->performSearch('admin.contacts.persons.search', [
        'search' => 'lastname:De Bruin;',
    ]);

    $this->assertEntityFound($resp, $p->id);
});

test('person search accepts plain email via query param', function () {
    $email = 'jane.query@example.com';
    $p = Person::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => 'Query',
        'emails'     => [
            ['value' => $email, 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'user_id'    => $this->user->id,
    ]);

    // Send as plain query so backend's email-normalization path is exercised
    $resp = $this->performSearch('admin.contacts.persons.search', [
        'query' => $email,
    ]);

    $this->assertEntityFound($resp, $p->id);
});

test('person search accepts plain name via query param', function () {
    $p = Person::factory()->create([
        'first_name' => 'Karel',
        'last_name'  => 'Zoeker',
        'user_id'    => $this->user->id,
    ]);

    $resp = $this->performSearch('admin.contacts.persons.search', [
        'query' => 'Karel Zoek',
    ]);

    $this->assertEntityFound($resp, $p->id);
});

test('person search matches partial email via query param', function () {
    $email = 'harmkevdmeer@outlook.com';
    $p = Person::factory()->create([
        'first_name' => 'Harmke',
        'last_name'  => 'vd Meer',
        'emails'     => [
            ['value' => $email, 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'user_id'    => $this->user->id,
    ]);

    // Search by partial local-part of the email (as users often do)
    $resp = $this->performSearch('admin.contacts.persons.search', [
        'query' => 'harmkev',
    ]);

    $this->assertEntityFound($resp, $p->id);
});

test('person search by organization.name works', function () {
    $org = Organization::factory()->create(['name' => 'Kuijer BV']);
    $p = Person::factory()->create([
        'first_name'      => 'Piet',
        'last_name'       => 'Jansen',
        'organization_id' => $org->id,
        'user_id'         => $this->user->id,
    ]);

    $resp = $this->performSearch('admin.contacts.persons.search', [
        'search'       => 'organization.name:Kuijer;',
        'searchFields' => 'organization.name:like;',
    ]);

    $this->assertEntityFound($resp, $p->id);
});

test('person search by email and phone works', function () {
    $p = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [
            ['value' => 'john.doe@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'phones' => [
            ['value' => '0687654321', 'label' => ContactLabel::Relatie->value, 'is_default' => true],
        ],
        'user_id'    => $this->user->id,
    ]);

    // Email
    $respEmail = $this->performSearch('admin.contacts.persons.search', [
        'search'       => 'emails:john.doe@example.com;',
        'searchFields' => 'emails:like;',
    ]);
    $this->assertEntityFound($respEmail, $p->id);

    // Phone
    $respPhone = $this->performSearch('admin.contacts.persons.search', [
        'search'       => 'phones:0687654321;',
        'searchFields' => 'phones:like;',
    ]);
    $this->assertEntityFound($respPhone, $p->id);
});

test('person search by phone works', function () {
    $p = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [
            ['value' => 'john.doe@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'phones' => [
            ['value' => '0687654321', 'label' => ContactLabel::Relatie->value, 'is_default' => true],
        ],
        'user_id'    => $this->user->id,
    ]);

    $respPhone = $this->performSearch('admin.contacts.persons.search', [
        'query' => '6876543',
    ]);
    $this->assertEntityFound($respPhone, $p->id);
});

test('person search converts invalid field tokens to name search', function () {
    Person::factory()->create([
        'first_name' => 'Desiree',
        'last_name'  => 'Test',
        'user_id'    => $this->user->id,
    ]);

    // Invalid field token 'des:1' should be converted to name search
    $resp = $this->performSearch('admin.contacts.persons.search', [
        'search' => 'des:1',
    ]);

    // The invalid field token should be sanitized and converted to name search
    // Since '1' doesn't match 'Desiree' or 'Test', we might not find results
    // But the important thing is it doesn't return 400 error
    $resp->assertOk();
});

test('person search handles invalid field tokens and finds matching names', function () {
    $p = Person::factory()->create([
        'first_name' => 'Desiree',
        'last_name'  => 'Test',
        'user_id'    => $this->user->id,
    ]);

    // Invalid field token 'des:Desiree' should be converted to name search
    $resp = $this->performSearch('admin.contacts.persons.search', [
        'search' => 'des:Desiree',
    ]);

    // Should find the person because 'Desiree' matches first_name
    $this->assertEntityFound($resp, $p->id);
});

test('person search handles invalid field tokens like des:1 without error', function () {
    Person::factory()->create([
        'first_name' => 'Desiree',
        'last_name'  => 'Test',
        'user_id'    => $this->user->id,
    ]);

    // Simulate the exact scenario: search=des:1 (invalid field 'des')
    // This should be sanitized and converted to name search for '1'
    $resp = $this->performSearch('admin.contacts.persons.search', [
        'search' => 'des:1',
    ]);

    // Should not return 500 error, should return 200 OK
    // The search term '1' won't match, but that's fine - no error should occur
    $resp->assertOk();
});

test('person search handles plain text query with colon correctly', function () {
    $p = Person::factory()->create([
        'first_name' => 'Desiree',
        'last_name'  => 'Test',
        'user_id'    => $this->user->id,
    ]);

    // Plain text query 'desiree' should work normally
    $resp = $this->performSearch('admin.contacts.persons.search', [
        'query' => 'desiree',
    ]);

    $this->assertEntityFound($resp, $p->id);
});

test('person search handles search parameter without searchFields', function () {
    $p = Person::factory()->create([
        'first_name' => 'Desiree',
        'last_name'  => 'Test',
        'user_id'    => $this->user->id,
    ]);

    // This is the exact scenario from the frontend: search=desiree without searchFields
    // The backend should convert this to proper name search format
    $resp = $this->performSearch('admin.contacts.persons.search', [
        'search' => 'desiree',
    ]);

    // Should find the person because 'desiree' matches first_name
    $this->assertEntityFound($resp, $p->id);
});

test('person search handles search parameter without searchFields for multi-word query', function () {
    $p = Person::factory()->create([
        'first_name' => 'Desiree',
        'last_name'  => 'Test',
        'user_id'    => $this->user->id,
    ]);

    // Multi-word search without searchFields should also work
    $resp = $this->performSearch('admin.contacts.persons.search', [
        'search' => 'Desiree Test',
    ]);

    // Should find the person because both words match
    $this->assertEntityFound($resp, $p->id);
});

test('person search returns 400 for invalid field in searchFields only', function () {
    // If searchFields contains invalid field but search doesn't, still validate searchFields
    $resp = $this->performSearch('admin.contacts.persons.search', [
        'search'       => 'test',
        'searchFields' => 'invalid_field:like;',
    ]);

    // searchFields validation should still return 400
    $resp->assertStatus(400);
});

test('person search with lead_id calculates match scores', function () {
    // Create a lead with matching data
    $pipeline = Pipeline::first() ?? Pipeline::factory()->create(['is_default' => 1]);
    $stage = Stage::where('lead_pipeline_id', $pipeline->id)->first() ?? Stage::factory()->create([
        'lead_pipeline_id' => $pipeline->id,
        'sort_order'       => 1,
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'Desiree',
        'last_name'              => 'Test',
        'emails'                 => [['value' => 'desiree.test@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
        'phones'                 => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value, 'is_default' => true]],
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
    ]);

    // Create a person that matches the lead
    $matchingPerson = Person::factory()->create([
        'first_name' => 'Desiree',
        'last_name'  => 'Test',
        'emails'     => [['value' => 'desiree.test@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
        'phones'     => [['value' => '0612345678', 'label' => ContactLabel::Relatie->value, 'is_default' => true]],
        'user_id'    => $this->user->id,
    ]);

    // Create a person that partially matches (only name, not email/phone)
    $partialMatchPerson = Person::factory()->create([
        'first_name' => 'Desiree',
        'last_name'  => 'Test',
        'emails'     => [['value' => 'different@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
        'phones'     => [['value' => '0687654321', 'label' => ContactLabel::Relatie->value, 'is_default' => true]],
        'user_id'    => $this->user->id,
    ]);

    // Create a person that doesn't match at all
    $nonMatchingPerson = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john.doe@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
        'phones'     => [['value' => '0699999999', 'label' => ContactLabel::Relatie->value, 'is_default' => true]],
        'user_id'    => $this->user->id,
    ]);

    // Search with lead_id parameter
    $resp = $this->performSearch('admin.contacts.persons.search', [
        'search'  => 'desi',
        'lead_id' => $lead->id,
    ]);

    // Should return 200 OK
    $resp->assertOk();

    // Get the response data
    $data = $resp->json('data') ?? $resp->json();
    expect($data)->toBeArray();

    // Find persons in the response
    $matchingPersonData = collect($data)->firstWhere('id', $matchingPerson->id);
    $partialMatchPersonData = collect($data)->firstWhere('id', $partialMatchPerson->id);
    $nonMatchingPersonData = collect($data)->firstWhere('id', $nonMatchingPerson->id);

    // Matching person should be found with a match score
    expect($matchingPersonData)->not->toBeNull();
    expect($matchingPersonData)->toHaveKey('match_score');
    expect($matchingPersonData)->toHaveKey('match_score_percentage');
    expect($matchingPersonData['match_score'])->toBeGreaterThan(0);

    // Partial match person should also be found if score > 0
    if ($partialMatchPersonData) {
        expect($partialMatchPersonData['match_score'])->toBeGreaterThan(0);
        // Partial match should have lower score than full match
        expect($matchingPersonData['match_score'])->toBeGreaterThan($partialMatchPersonData['match_score']);
    }

    // Non-matching person should not be in results (or have score 0 and be filtered out)
    if ($nonMatchingPersonData) {
        expect($nonMatchingPersonData['match_score'] ?? 0)->toBe(0);
    }

    // Results should be sorted by match score (highest first)
    $scores = collect($data)->pluck('match_score')->filter(fn ($score) => $score > 0)->toArray();
    $sortedScores = $scores;
    rsort($sortedScores);
    expect($scores)->toBe($sortedScores);
});
