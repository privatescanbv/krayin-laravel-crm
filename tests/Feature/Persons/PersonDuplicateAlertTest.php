<?php

use App\Enums\ContactLabel;
use App\Enums\DuplicateEntityType;
use App\Services\DuplicateFalsePositiveService;
use App\Services\PersonDuplicateCacheService;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Cache;
use Webkul\Contact\Models\Person;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();
    Cache::flush();
    $this->cacheService = app(PersonDuplicateCacheService::class);
});

test('reading cached duplicates sets has_duplicates on both matching persons', function () {
    $p1 = Person::factory()->create([
        'first_name' => 'Cache',
        'last_name'  => 'One',
        'emails'     => [['value' => 'dup.flag@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);
    $p2 = Person::factory()->create([
        'first_name' => 'Cache',
        'last_name'  => 'Two',
        'emails'     => [['value' => 'dup.flag@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    $this->cacheService->getCachedDuplicates($p1->id);

    expect($p1->fresh()->has_duplicates)->toBeTrue()
        ->and($p2->fresh()->has_duplicates)->toBeTrue()
        ->and($this->cacheService->countPersonsWithDuplicates())->toBe(2);
});

test('false positives clear the has_duplicates flag', function () {
    $p1 = Person::factory()->create([
        'first_name' => 'False',
        'last_name'  => 'One',
        'emails'     => [['value' => 'dup.fp@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);
    $p2 = Person::factory()->create([
        'first_name' => 'False',
        'last_name'  => 'Two',
        'emails'     => [['value' => 'dup.fp@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    $this->cacheService->getCachedDuplicates($p1->id);
    expect($p1->fresh()->has_duplicates)->toBeTrue();

    app(DuplicateFalsePositiveService::class)->storeForEntities(
        DuplicateEntityType::PERSON,
        [$p1->id, $p2->id]
    );

    $this->cacheService->getCachedDuplicates($p1->id);
    $this->cacheService->getCachedDuplicates($p2->id);

    expect($p1->fresh()->has_duplicates)->toBeFalse()
        ->and($p2->fresh()->has_duplicates)->toBeFalse()
        ->and($this->cacheService->countPersonsWithDuplicates())->toBe(0);
});

test('index rebuild flags existing duplicates', function () {
    Person::factory()->create([
        'first_name' => 'Index',
        'last_name'  => 'One',
        'emails'     => [['value' => 'dup.index@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);
    Person::factory()->create([
        'first_name' => 'Index',
        'last_name'  => 'Two',
        'emails'     => [['value' => 'dup.index@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    expect($this->cacheService->countPersonsWithDuplicates())->toBe(0);

    $this->artisan('duplicates:refresh-cache --index')->assertSuccessful();

    expect($this->cacheService->countPersonsWithDuplicates())->toBe(2);
});

test('persons index url includes the duplicates filter', function () {
    expect($this->cacheService->personsIndexUrlWithDuplicateFilter())
        ->toContain('filters')
        ->toContain('has_duplicates')
        ->toContain('1');
});
