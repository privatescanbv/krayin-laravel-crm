<?php

use App\Enums\PreferredLanguage;
use App\Models\Address;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();
    $this->personRepository = app(PersonRepository::class);
});

test('it copies bsn, organization, preferred language and is_active from the chosen duplicate', function () {
    $organization = Organization::factory()->create();

    $primary = Person::factory()->create([
        'national_identification_number' => '111222333',
        'organization_id'                => null,
        'preferred_language'             => PreferredLanguage::NL,
        'is_active'                      => true,
    ]);

    $duplicate = Person::factory()->create([
        'national_identification_number' => '999888777',
        'organization_id'                => $organization->id,
        'preferred_language'             => PreferredLanguage::EN,
        'is_active'                      => false,
    ]);

    $merged = $this->personRepository->mergePersons($primary->id, [$duplicate->id], [
        'national_identification_number' => $duplicate->id,
        'organization_id'                => $duplicate->id,
        'preferred_language'             => $duplicate->id,
        'is_active'                      => $duplicate->id,
    ]);

    expect($merged->national_identification_number)->toBe('999888777')
        ->and($merged->organization_id)->toBe($organization->id)
        ->and($merged->preferred_language)->toBe(PreferredLanguage::EN)
        ->and($merged->is_active)->toBeFalse();
});

test('it keeps the primary value when the chosen duplicate has an empty field', function () {
    $organization = Organization::factory()->create();

    $primary = Person::factory()->create(['organization_id' => $organization->id]);
    $duplicate = Person::factory()->create(['organization_id' => null]);

    $merged = $this->personRepository->mergePersons($primary->id, [$duplicate->id], [
        'organization_id' => $duplicate->id,
    ]);

    expect($merged->organization_id)->toBe($organization->id);
});

test('it adopts the address of the duplicate when the primary has none', function () {
    $primary = Person::factory()->create(['address_id' => null]);
    $duplicate = Person::factory()->create([
        'address_id' => Address::factory()->create()->id,
    ]);

    $merged = $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($merged->fresh()->address_id)->toBe($duplicate->address_id);
});
