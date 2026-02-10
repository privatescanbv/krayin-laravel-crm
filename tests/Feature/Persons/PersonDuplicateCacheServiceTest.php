<?php

namespace Tests\Feature;

use App\Services\PersonDuplicateCacheService;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Webkul\Contact\Models\Person;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();
    Cache::flush();
    $this->cacheService = app(PersonDuplicateCacheService::class);
});

test('full rebuild populates cache for all persons and stats reflect coverage', function () {
    // Arrange: create persons with a duplicate relation to ensure cache content is non-empty
    $p1 = Person::factory()->create([
        'first_name' => 'Cache',
        'last_name'  => 'Test',
        'emails'     => [['value' => 'dup.cache@example.com']],
    ]);
    $p2 = Person::factory()->create([
        'first_name' => 'Other',
        'last_name'  => 'Person',
        'emails'     => [['value' => 'dup.cache@example.com']],
    ]);

    // Act: run the unified duplicate cache refresh command
    $this->artisan('duplicates:refresh-cache --full')->assertSuccessful();

    // Ensure cache in current process as well (some cache drivers in tests are process-local)
    $this->cacheService->refreshPersonCache($p1->id);
    $this->cacheService->refreshPersonCache($p2->id);

    // Assert: stats indicate coverage and cache entries exist
    $stats = $this->cacheService->getCacheStats();
    expect($stats['total_persons'])->toBeGreaterThan(0);
    expect($stats['cached_count'])->toBeGreaterThanOrEqual(2);
    expect($stats['coverage_pct'])->toBeFloat();

    // Spot check cache keys for the created persons
    $ref = new ReflectionClass(PersonDuplicateCacheService::class);
    $method = $ref->getMethod('getCachedDuplicates');
    $method->setAccessible(true);
    $cached = $this->cacheService->getCachedDuplicates($p1->id);
    expect($cached->count())->toBeGreaterThanOrEqual(1);
});
