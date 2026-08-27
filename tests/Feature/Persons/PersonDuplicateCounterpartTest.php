<?php

namespace Tests\Feature\Persons;

use App\Enums\ContactLabel;
use App\Services\PersonDuplicateCacheService;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->cacheService = app(PersonDuplicateCacheService::class);
    $this->personRepository = app(PersonRepository::class);
});

/** @param  array<int, string>  $values */
function emailField(string ...$values): array
{
    return array_map(fn ($v) => ['value' => $v, 'label' => ContactLabel::Eigen->value], $values);
}

/** @param  array<int, string>  $values */
function phoneField(string ...$values): array
{
    return array_map(fn ($v) => ['value' => $v, 'label' => ContactLabel::Eigen->value], $values);
}

function warmFlags(Person ...$persons): void
{
    $service = app(PersonDuplicateCacheService::class);
    foreach ($persons as $person) {
        $service->getCachedDuplicates($person->id);
    }
}

test('merging clears the has_duplicates flag of a person that only matched the merged-away one', function () {
    $a = Person::factory()->create(['first_name' => 'Alpha', 'last_name' => 'One', 'emails' => emailField('shared-ab@example.com')]);
    $b = Person::factory()->create([
        'first_name' => 'Beta', 'last_name' => 'Two',
        'emails'     => emailField('shared-ab@example.com'),
        'phones'     => phoneField('+31600000001'),
    ]);
    $c = Person::factory()->create(['first_name' => 'Gamma', 'last_name' => 'Three', 'phones' => phoneField('+31600000001')]);

    warmFlags($a, $b, $c);
    expect($c->fresh()->has_duplicates)->toBeTrue();

    // C only ever matched B (shared phone). B is merged into A; C now matches nobody.
    $this->personRepository->mergePersons($a->id, [$b->id]);

    expect($c->fresh()->has_duplicates)->toBeFalse()
        ->and($this->cacheService->countPersonsWithDuplicates())->toBe(0);
});

test('merging keeps the counterpart flag when it still matches someone else', function () {
    $a = Person::factory()->create(['first_name' => 'Alpha', 'last_name' => 'One', 'emails' => emailField('shared-ab@example.com')]);
    $b = Person::factory()->create([
        'first_name' => 'Beta', 'last_name' => 'Two',
        'emails'     => emailField('shared-ab@example.com'),
        'phones'     => phoneField('+31600000002'),
    ]);
    $c = Person::factory()->create([
        'first_name' => 'Gamma', 'last_name' => 'Three',
        'phones'     => phoneField('+31600000002'),
        'emails'     => emailField('shared-cd@example.com'),
    ]);
    $d = Person::factory()->create(['first_name' => 'Delta', 'last_name' => 'Four', 'emails' => emailField('shared-cd@example.com')]);

    warmFlags($a, $b, $c, $d);

    $this->personRepository->mergePersons($a->id, [$b->id]);

    // C still shares an email with D.
    expect($c->fresh()->has_duplicates)->toBeTrue()
        ->and($d->fresh()->has_duplicates)->toBeTrue();
});

test('deleting a person clears the has_duplicates flag of its former counterpart', function () {
    $b = Person::factory()->create(['first_name' => 'Beta', 'last_name' => 'Two', 'phones' => phoneField('+31600000003')]);
    $c = Person::factory()->create(['first_name' => 'Gamma', 'last_name' => 'Three', 'phones' => phoneField('+31600000003')]);

    warmFlags($b, $c);
    expect($c->fresh()->has_duplicates)->toBeTrue();

    $b->delete();

    expect($c->fresh()->has_duplicates)->toBeFalse()
        ->and($this->cacheService->countPersonsWithDuplicates())->toBe(0);
});

test('editing a person so it no longer matches clears the OLD counterpart flag immediately', function () {
    $a = Person::factory()->create(['first_name' => 'Alpha', 'last_name' => 'One', 'emails' => emailField('ratchet@example.com')]);
    $b = Person::factory()->create(['first_name' => 'Beta', 'last_name' => 'Two', 'emails' => emailField('ratchet@example.com')]);

    warmFlags($a, $b);
    expect($a->fresh()->has_duplicates)->toBeTrue()
        ->and($b->fresh()->has_duplicates)->toBeTrue();

    // Only B is touched; A must still be cleared (it was only matching B).
    $b->update(['emails' => emailField('no-longer-matching@example.com')]);

    expect($a->fresh()->has_duplicates)->toBeFalse()
        ->and($b->fresh()->has_duplicates)->toBeFalse()
        ->and($this->cacheService->countPersonsWithDuplicates())->toBe(0);
});

test('editing a person into a new match flags the new counterpart immediately', function () {
    $a = Person::factory()->create(['first_name' => 'Alpha', 'last_name' => 'One', 'emails' => emailField('new-match@example.com')]);
    $b = Person::factory()->create(['first_name' => 'Beta', 'last_name' => 'Two', 'emails' => emailField('unrelated@example.com')]);

    warmFlags($a, $b);
    expect($a->fresh()->has_duplicates)->toBeFalse();

    $b->update(['emails' => emailField('new-match@example.com')]);

    expect($a->fresh()->has_duplicates)->toBeTrue()
        ->and($b->fresh()->has_duplicates)->toBeTrue();
});

test('viewing a person page self-heals a stale has_duplicates flag', function () {
    $this->actingAs(getDefaultAdmin(), 'user');

    $person = Person::factory()->create(['has_duplicates' => true]); // stale: no real duplicate

    $this->get(route('admin.contacts.persons.view', $person->id))->assertOk();

    expect($person->fresh()->has_duplicates)->toBeFalse();
});
