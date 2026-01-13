<?php

namespace Tests\Feature;

use App\Enums\DuplicateEntityType;
use App\Services\DuplicateFalsePositiveService;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    // Reduce side effects during tests
    Person::unsetEventDispatcher();
    $this->personRepository = app(PersonRepository::class);
});

test('it detects duplicate persons by email', function () {
    $p1 = Person::factory()->create([
        'first_name' => 'Marcus',
        'last_name'  => 'Emailtest',
        'emails'     => [
            ['value' => 'shared.person@example.com', 'label' => 'work'],
        ],
    ]);

    $p2 = Person::factory()->create([
        'first_name' => 'Natasha',
        'last_name'  => 'Differentname',
        'emails'     => [
            ['value' => 'shared.person@example.com', 'label' => 'home'],
        ],
    ]);

    $duplicates = $this->personRepository->findPotentialDuplicates($p1);

    expect($duplicates->count())->toBe(1);
    expect($duplicates->first()->id)->toBe($p2->id);
});

test('it detects duplicate persons by email with value-only structure', function () {
    $p1 = Person::factory()->create([
        'first_name' => 'ValueOnly',
        'last_name'  => 'Email',
        'emails'     => [
            ['value' => 'value.only@example.com'],
        ],
    ]);

    $p2 = Person::factory()->create([
        'first_name' => 'Other',
        'last_name'  => 'Person',
        'emails'     => [
            ['value' => 'value.only@example.com'],
        ],
    ]);

    $duplicates = $this->personRepository->findPotentialDuplicatesDirectly($p1);

    expect($duplicates->count())->toBe(1);
    expect($duplicates->first()->id)->toBe($p2->id);
});

test('it detects duplicate persons by phone', function () {
    $p1 = Person::factory()->create([
        'first_name' => 'Alexander',
        'last_name'  => 'Phonetest',
        'phones'     => [
            ['value' => '+1234567890', 'label' => 'mobile'],
        ],
    ]);

    $p2 = Person::factory()->create([
        'first_name' => 'Bethany',
        'last_name'  => 'Differentname',
        'phones'     => [
            ['value' => '+1234567890', 'label' => 'work'],
        ],
    ]);

    $duplicates = $this->personRepository->findPotentialDuplicates($p1);

    expect($duplicates->count())->toBe(1);
    expect($duplicates->first()->id)->toBe($p2->id);
});

test('it excludes self from duplicate detection', function () {
    $p = Person::factory()->create([
        'first_name' => 'Selftest',
        'last_name'  => 'Exclusion',
        'emails'     => [
            ['value' => 'selftest.person@example.com', 'label' => 'work'],
        ],
    ]);

    $duplicates = $this->personRepository->findPotentialDuplicates($p);

    expect($duplicates->count())->toBe(0);
    expect($duplicates->contains('id', $p->id))->toBeFalse();
});

test('it returns empty collection when no duplicate persons exist', function () {
    $p = Person::factory()->create([
        'first_name' => 'Zephyr',
        'last_name'  => 'Quintessential',
        'emails'     => [
            ['value' => 'zephyr.unique.person@example.com', 'label' => 'work'],
        ],
        'phones'     => [
            ['value' => '+9999999999', 'label' => 'mobile'],
        ],
    ]);

    $duplicates = $this->personRepository->findPotentialDuplicates($p);

    expect($duplicates->count())->toBe(0);
});

test('it hides duplicates when marked as false positive', function () {
    $p1 = Person::factory()->create([
        'first_name' => 'False',
        'last_name'  => 'Positive',
        'emails'     => [
            ['value' => 'fp.person@example.com', 'label' => 'work'],
        ],
    ]);

    $p2 = Person::factory()->create([
        'first_name' => 'Other',
        'last_name'  => 'Person',
        'emails'     => [
            ['value' => 'fp.person@example.com', 'label' => 'home'],
        ],
    ]);

    // Sanity check: duplicates exist before marking
    $duplicatesBefore = $this->personRepository->findPotentialDuplicates($p1);
    expect($duplicatesBefore->pluck('id')->all())->toContain($p2->id);

    app(DuplicateFalsePositiveService::class)->storeForEntities(
        DuplicateEntityType::PERSON,
        [$p1->id, $p2->id]
    );

    $duplicatesAfter = $this->personRepository->findPotentialDuplicates($p1);
    expect($duplicatesAfter->count())->toBe(0);
});
