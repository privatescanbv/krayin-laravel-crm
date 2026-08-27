<?php

use App\Enums\ContactLabel;
use App\Models\Address;
use App\Services\PersonSuggestionService;
use Tests\Feature\Concerns\ControllerSearchTestHelpers;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

uses(ControllerSearchTestHelpers::class);

beforeEach(function () {
    $this->setUpSearchTest();
});

function makePersonSuggestionLead(array $overrides = []): Lead
{
    $pipeline = Pipeline::first() ?? Pipeline::factory()->create(['is_default' => 1]);
    $stage = Stage::where('lead_pipeline_id', $pipeline->id)->first()
        ?? Stage::factory()->create(['lead_pipeline_id' => $pipeline->id, 'sort_order' => 1]);

    return Lead::factory()->create(array_merge([
        'first_name'             => 'Linda',
        'last_name'              => 'Jansen',
        'emails'                 => [],
        'phones'                 => [],
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
        'user_id'                => test()->user->id,
    ], $overrides));
}

function makePersonSuggestionPerson(array $overrides = []): Person
{
    return Person::factory()->create(array_merge([
        'emails'  => [],
        'phones'  => [],
        'user_id' => test()->user->id,
    ], $overrides));
}

function fetchPersonAutoSuggestions(Lead $lead)
{
    return test()->getJson(route('admin.contacts.persons.search', [
        'lead_id' => $lead->id,
    ]));
}

test('auto-suggest finds a person with a different first name but the same email', function () {
    $lead = makePersonSuggestionLead([
        'first_name' => 'Johannes',
        'last_name'  => 'Jansen',
        'emails'     => [['value' => 'jansen@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    $match = makePersonSuggestionPerson([
        'first_name' => 'Jan',
        'last_name'  => 'Jansen',
        'emails'     => [['value' => 'jansen@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    $resp = fetchPersonAutoSuggestions($lead);

    test()->assertEntityFound($resp, $match->id);
    $row = collect($resp->json('data'))->firstWhere('id', $match->id);
    expect($row['match_reasons'])->toContain(PersonSuggestionService::REASON_EMAIL)
        ->and($row['match_reasons'])->toContain(PersonSuggestionService::REASON_FIRST_NAME_DIFFERS)
        ->and($row['match_score'])->toBeGreaterThan(0);
});

test('auto-suggest finds a person with a different first name but the same phone', function () {
    $lead = makePersonSuggestionLead([
        'first_name' => 'Johannes',
        'last_name'  => 'Visser',
        'phones'     => [['value' => '+31612345678', 'label' => ContactLabel::Eigen->value]],
    ]);

    $match = makePersonSuggestionPerson([
        'first_name' => 'Jan',
        'last_name'  => 'Visser',
        'phones'     => [['value' => '0612345678', 'label' => ContactLabel::Eigen->value]],
    ]);

    $resp = fetchPersonAutoSuggestions($lead);

    test()->assertEntityFound($resp, $match->id);
    $row = collect($resp->json('data'))->firstWhere('id', $match->id);
    expect($row['match_reasons'])->toContain(PersonSuggestionService::REASON_PHONE);
});

test('auto-suggest finds Anna Maria vs Anna with the same last name', function () {
    $lead = makePersonSuggestionLead([
        'first_name' => 'Anna Maria',
        'last_name'  => 'Bakker',
    ]);

    $match = makePersonSuggestionPerson([
        'first_name' => 'Anna',
        'last_name'  => 'Bakker',
    ]);

    $resp = fetchPersonAutoSuggestions($lead);

    test()->assertEntityFound($resp, $match->id);
    $row = collect($resp->json('data'))->firstWhere('id', $match->id);
    expect($row['match_reasons'])->toContain(PersonSuggestionService::REASON_FIRST_NAME_SIMILAR)
        ->and($row['match_reasons'])->toContain(PersonSuggestionService::REASON_LAST_NAME);
});

test('auto-suggest still finds an exact first and last name match', function () {
    $lead = makePersonSuggestionLead([
        'first_name' => 'Desiree',
        'last_name'  => 'Test',
        'emails'     => [['value' => 'desiree.test@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    $match = makePersonSuggestionPerson([
        'first_name' => 'Desiree',
        'last_name'  => 'Test',
        'emails'     => [['value' => 'desiree.test@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    $resp = fetchPersonAutoSuggestions($lead);

    test()->assertEntityFound($resp, $match->id);
    $row = collect($resp->json('data'))->firstWhere('id', $match->id);
    expect($row['match_score'])->toBeGreaterThan(0)
        ->and($row['match_reasons'])->toContain(PersonSuggestionService::REASON_FIRST_NAME_SIMILAR)
        ->and($row['match_reasons'])->toContain(PersonSuggestionService::REASON_LAST_NAME)
        ->and($row['match_reasons'])->toContain(PersonSuggestionService::REASON_EMAIL);
});

test('auto-suggest does not list last-name-only hits without an extra signal', function () {
    $lead = makePersonSuggestionLead([
        'first_name' => 'Linda',
        'last_name'  => 'Jansen',
        'emails'     => [],
        'phones'     => [],
    ]);

    $noiseIds = [];
    for ($i = 0; $i < 8; $i++) {
        $noiseIds[] = makePersonSuggestionPerson([
            'first_name' => 'Piet'.$i,
            'last_name'  => 'Jansen',
        ])->id;
    }

    $resp = fetchPersonAutoSuggestions($lead);

    $resp->assertOk();
    $ids = collect($resp->json('data'))->pluck('id')->all();
    expect(array_intersect($ids, $noiseIds))->toBe([]);
});

test('auto-suggest finds a last-name match with the same date of birth', function () {
    $lead = makePersonSuggestionLead([
        'first_name'    => 'Linda',
        'last_name'     => 'Jansen',
        'date_of_birth' => '1984-05-17',
    ]);

    $match = makePersonSuggestionPerson([
        'first_name'    => 'Piet',
        'last_name'     => 'Jansen',
        'date_of_birth' => '1984-05-17',
    ]);

    makePersonSuggestionPerson([
        'first_name' => 'Klaas',
        'last_name'  => 'Jansen',
    ]);

    $resp = fetchPersonAutoSuggestions($lead);

    test()->assertEntityFound($resp, $match->id);
    $row = collect($resp->json('data'))->firstWhere('id', $match->id);
    expect($row['match_reasons'])->toContain(PersonSuggestionService::REASON_DOB)
        ->and($row['match_reasons'])->toContain(PersonSuggestionService::REASON_FIRST_NAME_DIFFERS);

    test()->assertEntityNotFound($resp, Person::query()->where('first_name', 'Klaas')->first()->id);
});

test('auto-suggest finds a last-name match with the same postal code', function () {
    $leadAddress = Address::factory()->create(['postal_code' => '1234AB', 'house_number' => '10']);
    $personAddress = Address::factory()->create(['postal_code' => '1234 AB', 'house_number' => '12']);

    $lead = makePersonSuggestionLead([
        'first_name' => 'Linda',
        'last_name'  => 'Groot',
        'address_id' => $leadAddress->id,
    ]);

    $match = makePersonSuggestionPerson([
        'first_name' => 'Piet',
        'last_name'  => 'Groot',
        'address_id' => $personAddress->id,
    ]);

    $resp = fetchPersonAutoSuggestions($lead);

    test()->assertEntityFound($resp, $match->id);
    $row = collect($resp->json('data'))->firstWhere('id', $match->id);
    expect($row['match_reasons'])->toContain(PersonSuggestionService::REASON_POSTAL_CODE);
});

test('auto-suggest respects individual view permission', function () {
    $otherUser = User::factory()->create(['view_permission' => 'global']);
    $this->user->update(['view_permission' => 'individual']);
    $this->actingAs($this->user->fresh(), 'user');

    $lead = makePersonSuggestionLead([
        'first_name' => 'Eva',
        'last_name'  => 'Kuijer',
        'emails'     => [['value' => 'eva@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    $visible = makePersonSuggestionPerson([
        'first_name' => 'Eva',
        'last_name'  => 'Kuijer',
        'emails'     => [['value' => 'eva@example.com', 'label' => ContactLabel::Eigen->value]],
        'user_id'    => $this->user->id,
    ]);

    $hidden = makePersonSuggestionPerson([
        'first_name' => 'Eva',
        'last_name'  => 'Kuijer',
        'emails'     => [['value' => 'eva@example.com', 'label' => ContactLabel::Eigen->value]],
        'user_id'    => $otherUser->id,
    ]);

    $resp = fetchPersonAutoSuggestions($lead);

    test()->assertEntityFound($resp, $visible->id);
    test()->assertEntityNotFound($resp, $hidden->id);
});

function fetchPersonSuggestionsFromFields(array $payload)
{
    return test()->postJson(route('admin.contacts.persons.suggest'), $payload);
}

test('create suggest finds person when phone formats differ (06 vs +31)', function () {
    $match = makePersonSuggestionPerson([
        'first_name' => 'Jan',
        'last_name'  => 'Visser',
        'phones'     => [['value' => '+31612345678', 'label' => ContactLabel::Eigen->value]],
    ]);

    $resp = fetchPersonSuggestionsFromFields([
        'first_name' => 'Johannes',
        'last_name'  => 'Visser',
        'phones'     => [['value' => '0612345678', 'label' => ContactLabel::Eigen->value]],
    ]);

    test()->assertEntityFound($resp, $match->id);
    $row = collect($resp->json('data'))->firstWhere('id', $match->id);
    expect($row['match_reasons'])->toContain(PersonSuggestionService::REASON_PHONE)
        ->and($row['match_score'])->toBeGreaterThan(0);
});

test('create suggest finds person when phone is stored as local and payload uses e164', function () {
    $match = makePersonSuggestionPerson([
        'first_name' => 'Jan',
        'last_name'  => 'De Vries',
        'phones'     => [['value' => '0687654321', 'label' => ContactLabel::Eigen->value]],
    ]);

    $resp = fetchPersonSuggestionsFromFields([
        'first_name' => 'Johannes',
        'last_name'  => 'De Vries',
        'phones'     => [['value' => '+31687654321', 'label' => ContactLabel::Eigen->value]],
    ]);

    test()->assertEntityFound($resp, $match->id);
    $row = collect($resp->json('data'))->firstWhere('id', $match->id);
    expect($row['match_reasons'])->toContain(PersonSuggestionService::REASON_PHONE);
});

test('create suggest finds person by email with different first name', function () {
    $match = makePersonSuggestionPerson([
        'first_name' => 'Jan',
        'last_name'  => 'Jansen',
        'emails'     => [['value' => 'jansen@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    $resp = fetchPersonSuggestionsFromFields([
        'first_name' => 'Johannes',
        'last_name'  => 'Jansen',
        'emails'     => [['value' => 'jansen@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    test()->assertEntityFound($resp, $match->id);
    $row = collect($resp->json('data'))->firstWhere('id', $match->id);
    expect($row['match_reasons'])->toContain(PersonSuggestionService::REASON_EMAIL)
        ->and($row['match_reasons'])->toContain(PersonSuggestionService::REASON_FIRST_NAME_DIFFERS);
});

test('create suggest finds last-name match with same date of birth', function () {
    $match = makePersonSuggestionPerson([
        'first_name'    => 'Piet',
        'last_name'     => 'Jansen',
        'date_of_birth' => '1984-05-17',
    ]);

    makePersonSuggestionPerson([
        'first_name' => 'Klaas',
        'last_name'  => 'Jansen',
    ]);

    $resp = fetchPersonSuggestionsFromFields([
        'first_name'    => 'Linda',
        'last_name'     => 'Jansen',
        'date_of_birth' => '1984-05-17',
    ]);

    test()->assertEntityFound($resp, $match->id);
    $row = collect($resp->json('data'))->firstWhere('id', $match->id);
    expect($row['match_reasons'])->toContain(PersonSuggestionService::REASON_DOB);

    test()->assertEntityNotFound($resp, Person::query()->where('first_name', 'Klaas')->first()->id);
});

test('create suggest finds last-name match with same postal code', function () {
    $personAddress = Address::factory()->create(['postal_code' => '1234 AB', 'house_number' => '12']);

    $match = makePersonSuggestionPerson([
        'first_name' => 'Piet',
        'last_name'  => 'Groot',
        'address_id' => $personAddress->id,
    ]);

    $resp = fetchPersonSuggestionsFromFields([
        'first_name' => 'Linda',
        'last_name'  => 'Groot',
        'address'    => ['postal_code' => '1234AB', 'house_number' => '10'],
    ]);

    test()->assertEntityFound($resp, $match->id);
    $row = collect($resp->json('data'))->firstWhere('id', $match->id);
    expect($row['match_reasons'])->toContain(PersonSuggestionService::REASON_POSTAL_CODE);
});

test('create suggest returns empty list for empty payload', function () {
    makePersonSuggestionPerson([
        'first_name' => 'Jan',
        'last_name'  => 'Niemand',
        'phones'     => [['value' => '0611111111', 'label' => ContactLabel::Eigen->value]],
    ]);

    $resp = fetchPersonSuggestionsFromFields([]);

    $resp->assertOk();
    expect($resp->json('data'))->toBe([]);
});

test('create suggest respects individual view permission', function () {
    $otherUser = User::factory()->create(['view_permission' => 'global']);
    $this->user->update(['view_permission' => 'individual']);
    $this->actingAs($this->user->fresh(), 'user');

    $visible = makePersonSuggestionPerson([
        'first_name' => 'Eva',
        'last_name'  => 'Kuijer',
        'emails'     => [['value' => 'eva.create@example.com', 'label' => ContactLabel::Eigen->value]],
        'user_id'    => $this->user->id,
    ]);

    $hidden = makePersonSuggestionPerson([
        'first_name' => 'Eva',
        'last_name'  => 'Kuijer',
        'emails'     => [['value' => 'eva.create@example.com', 'label' => ContactLabel::Eigen->value]],
        'user_id'    => $otherUser->id,
    ]);

    $resp = fetchPersonSuggestionsFromFields([
        'first_name' => 'Eva',
        'last_name'  => 'Kuijer',
        'emails'     => [['value' => 'eva.create@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    test()->assertEntityFound($resp, $visible->id);
    test()->assertEntityNotFound($resp, $hidden->id);
});

/**
 * Simulate create-lead UI: strong (email/phone) first, then match_score, keep top 10
 * (see person-suggestion-helpers.blade.php rankAndLimitSuggestions).
 */
function topTenPersonSuggestionsFromFields(array $payload)
{
    $resp = fetchPersonSuggestionsFromFields($payload);
    $resp->assertOk();

    $topTen = collect($resp->json('data'))
        ->sort(function ($a, $b) {
            $strong = function ($row) {
                $reasons = $row['match_reasons'] ?? [];

                return in_array('email', $reasons, true) || in_array('phone', $reasons, true) ? 1 : 0;
            };

            $strongDiff = $strong($b) <=> $strong($a);
            if ($strongDiff !== 0) {
                return $strongDiff;
            }

            return ($b['match_score'] ?? $b['match_score_percentage'] ?? 0)
                <=> ($a['match_score'] ?? $a['match_score_percentage'] ?? 0);
        })
        ->take(10)
        ->values();

    return [$resp, $topTen];
}

test('create suggest surfaces phone match amid many last-name candidates (Mark Bulthuis)', function () {
    $phoneMatch = makePersonSuggestionPerson([
        'first_name' => 'Pieter',
        'last_name'  => 'Janssen',
        'phones'     => [['value' => '0611251149', 'label' => ContactLabel::Eigen->value]],
    ]);

    // 15+ eligible last-name + similar-first hits outrank a format-mismatched phone
    // in calculateMatchScore (raw string compare), then UI keeps only top 10.
    for ($i = 0; $i < 15; $i++) {
        makePersonSuggestionPerson([
            'first_name' => 'Mark',
            'last_name'  => 'Bulthuis',
            'phones'     => [],
            'emails'     => [],
        ]);
    }

    [$resp, $topTen] = topTenPersonSuggestionsFromFields([
        'first_name' => 'Mark',
        'last_name'  => 'Bulthuis',
        'phones'     => [['value' => '+31611251149', 'label' => ContactLabel::Eigen->value]],
    ]);

    // Full API list should include the phone match (PersonSuggestionService finds by phone).
    test()->assertEntityFound($resp, $phoneMatch->id);

    $phoneRow = collect($resp->json('data'))->firstWhere('id', $phoneMatch->id);
    expect($phoneRow['match_reasons'])->toContain(PersonSuggestionService::REASON_PHONE);

    // Phone formats differ (E.164 vs local) — scoring must still treat them as a match
    // so the strong phone signal is not buried under name-only candidates.
    expect($phoneRow['match_score'])->toBeGreaterThan(0);

    // UI only shows top 10 by match_score — phone match must survive crowding.
    expect($topTen->pluck('id')->all())->toContain($phoneMatch->id);
});

test('create suggest finds exact Mark Bulthuis with matching phone when all three filled', function () {
    $match = makePersonSuggestionPerson([
        'first_name' => 'Mark',
        'last_name'  => 'Bulthuis',
        'phones'     => [['value' => '+31611251149', 'label' => ContactLabel::Eigen->value]],
    ]);

    for ($i = 0; $i < 15; $i++) {
        makePersonSuggestionPerson([
            'first_name' => 'Mark'.$i,
            'last_name'  => 'Bulthuis',
            'phones'     => [],
        ]);
    }

    [$resp, $topTen] = topTenPersonSuggestionsFromFields([
        'first_name' => 'Mark',
        'last_name'  => 'Bulthuis',
        'phones'     => [['value' => '+31611251149', 'label' => ContactLabel::Eigen->value]],
    ]);

    test()->assertEntityFound($resp, $match->id);
    expect($topTen->pluck('id')->all())->toContain($match->id);

    $row = collect($resp->json('data'))->firstWhere('id', $match->id);
    expect($row['match_reasons'])->toContain(PersonSuggestionService::REASON_PHONE)
        ->and($row['match_reasons'])->toContain(PersonSuggestionService::REASON_LAST_NAME)
        ->and($row['match_reasons'])->toContain(PersonSuggestionService::REASON_FIRST_NAME_SIMILAR);
});

test('create suggest finds phone-only match when last_name cleared (control)', function () {
    $phoneMatch = makePersonSuggestionPerson([
        'first_name' => 'Pieter',
        'last_name'  => 'Janssen',
        'phones'     => [['value' => '0611251149', 'label' => ContactLabel::Eigen->value]],
    ]);

    for ($i = 0; $i < 15; $i++) {
        makePersonSuggestionPerson([
            'first_name' => 'Crowd'.$i,
            'last_name'  => 'Bulthuis',
        ]);
    }

    [$resp, $topTen] = topTenPersonSuggestionsFromFields([
        'first_name' => 'Mark',
        'last_name'  => '',
        'phones'     => [['value' => '+31611251149', 'label' => ContactLabel::Eigen->value]],
    ]);

    test()->assertEntityFound($resp, $phoneMatch->id);
    expect($topTen->pluck('id')->all())->toContain($phoneMatch->id);
    expect(collect($resp->json('data'))->firstWhere('id', $phoneMatch->id)['match_reasons'])
        ->toContain(PersonSuggestionService::REASON_PHONE);
});
