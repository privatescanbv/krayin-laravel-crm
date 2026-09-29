<?php

use App\Enums\PreferredLanguage;
use App\Models\Address;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Cache;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();

    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutMiddleware(Authenticate::class);
});

test('viewing the duplicates page clears a stale has_duplicates flag left over from a merge', function () {
    // Person C used to match B (e.g. shared phone), so both got flagged. B is then merged
    // into A, which only clears/recomputes A and B's flags - C is left stale (MBS-366).
    $person = Person::factory()->create([
        'phones'         => [['value' => '+31651441908', 'label' => 'eigen']],
        'has_duplicates' => true,
    ]);

    $response = $this->get(route('admin.contacts.persons.duplicates.index', $person->id))
        ->assertOk();

    expect($response->viewData('duplicates'))->toBeEmpty()
        ->and($person->fresh()->has_duplicates)->toBeFalse();
});

test('viewing the duplicates page without duplicates invalidates the stale duplicate cache', function () {
    $person = Person::factory()->create(['has_duplicates' => true]);
    $other = Person::factory()->create();

    Cache::put('person_duplicates:'.$person->id, collect([$other->id]), 3600);
    Cache::put('person_duplicates:'.$other->id, collect([$person->id]), 3600);

    $this->get(route('admin.contacts.persons.duplicates.index', $person->id))
        ->assertOk();

    expect(Cache::has('person_duplicates:'.$person->id))->toBeFalse()
        ->and(Cache::has('person_duplicates:'.$other->id))->toBeTrue()
        ->and($person->fresh()->has_duplicates)->toBeFalse();
});

test('viewing the duplicates page with duplicates keeps the cache', function () {
    $person = Person::factory()->create();
    Person::factory()->create([
        'first_name' => $person->first_name,
        'last_name'  => $person->last_name,
    ]);

    Cache::put('person_duplicates:'.$person->id, collect([123]), 3600);

    $this->get(route('admin.contacts.persons.duplicates.index', $person->id))
        ->assertOk();

    expect(Cache::has('person_duplicates:'.$person->id))->toBeTrue();
});

test('person duplicates merge view loads when primary person has phone numbers', function () {
    $person = Person::factory()->create([
        'phones' => [
            ['value' => '+31651441908', 'label' => 'eigen'],
        ],
    ]);

    $response = $this->get(route('admin.contacts.persons.duplicates.index', $person->id));

    $response->assertOk();
});

test('the merge view exposes address and the extra selectable fields', function () {
    $organization = Organization::factory()->create(['name' => 'Test Org BV']);
    $address = Address::factory()->create([
        'street'       => 'Voorbeeldstraat',
        'house_number' => '10',
        'postal_code'  => '1234AB',
        'city'         => 'Utrecht',
    ]);

    $person = Person::factory()->create([
        'organization_id'                => $organization->id,
        'address_id'                     => $address->id,
        'national_identification_number' => '123456789',
        'preferred_language'             => PreferredLanguage::DE,
        'is_active'                      => false,
        'job_title'                      => 'Chirurg',
    ]);

    // A second person matching on name so the duplicates page has content.
    Person::factory()->create([
        'first_name' => $person->first_name,
        'last_name'  => $person->last_name,
    ]);

    $personData = $this->get(route('admin.contacts.persons.duplicates.index', $person->id))
        ->assertOk()
        ->viewData('personData');

    expect($personData['first_name'])->toBe($person->first_name)
        ->and($personData['last_name'])->toBe($person->last_name)
        ->and($personData['organization_name'])->toBe('Test Org BV')
        ->and($personData['organization_id'])->toBe($organization->id)
        ->and($personData['national_identification_number'])->toBe('123456789')
        ->and($personData['preferred_language'])->toBe('de')
        ->and($personData['preferred_language_label'])->toBe('Duits')
        ->and($personData['is_active'])->toBeFalse()
        ->and($personData['is_active_label'])->toBe('Inactief')
        ->and($personData['job_title'])->toBe('Chirurg')
        ->and($personData['address'])->not->toBeNull()
        ->and($personData['has_portal_account'])->toBeFalse();
});

test('the merge view exposes portal flag, view links and make-primary action', function () {
    $person = Person::factory()->create([
        'keycloak_user_id' => 'kc-primary',
    ]);

    Person::factory()->create([
        'first_name'       => $person->first_name,
        'last_name'        => $person->last_name,
        'keycloak_user_id' => 'kc-duplicate',
    ]);

    $response = $this->get(route('admin.contacts.persons.duplicates.index', $person->id))
        ->assertOk()
        ->assertSee('Maak primair', false)
        ->assertSee('icon-eye', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('portaal', false);

    $personData = $response->viewData('personData');
    $duplicatesData = $response->viewData('duplicatesData');

    expect($personData['has_portal_account'])->toBeTrue()
        ->and(collect($duplicatesData)->pluck('has_portal_account')->contains(true))->toBeTrue();
});
