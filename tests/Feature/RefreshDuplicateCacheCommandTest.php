<?php

namespace Tests\Feature;

use App\Enums\ContactLabel;
use App\Services\LeadDuplicateCacheService;
use App\Services\PersonDuplicateCacheService;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Cache;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();
    Lead::unsetEventDispatcher();
    Cache::flush();
    $this->personCache = app(PersonDuplicateCacheService::class);
    $this->leadCache = app(LeadDuplicateCacheService::class);
});

// The incremental refresh only looks at `updated_at` (see RefreshDuplicateCache::handle), not
// `created_at`. This confirms that assumption holds: Eloquent sets `updated_at` on insert too, so a
// brand-new person still falls inside the "last 24h" window and gets its cache built on the next run.
test('incremental refresh picks up a person created since the last run', function () {
    // An older, otherwise-matching person outside the incremental window - only present so the new
    // person actually has a duplicate to be matched against.
    $existing = Person::factory()->create([
        'first_name' => 'Existing',
        'last_name'  => 'Match',
        'emails'     => [['value' => 'new.person.dup@example.com', 'label' => ContactLabel::Eigen->value]],
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);

    $newPerson = Person::factory()->create([
        'first_name' => 'Brand',
        'last_name'  => 'New',
        'emails'     => [['value' => 'new.person.dup@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    expect($newPerson->updated_at->greaterThan(now()->subDay()))->toBeTrue();

    $this->artisan('duplicates:refresh-cache')->assertSuccessful();

    // getCachedDuplicates already returns a collection of ids, not entities.
    $cached = $this->personCache->getCachedDuplicates($newPerson->id);

    expect($cached)->toContain($existing->id);
});

// Leads use the widened window (LeadRepository::DUPLICATE_SEARCH_PERIOD_WEEKS), not `updated_at`
// in the last 24h - a lead created within the window but untouched since still needs its cache
// refreshed, e.g. because a newer matching lead has since appeared.
test('incremental refresh picks up a lead within the duplicate search window even if untouched', function () {
    $threeWeeksOld = Lead::factory()->create([
        'first_name' => 'Untouched',
        'last_name'  => 'ButRecent',
        'emails'     => [['value' => 'window.dup@example.com', 'label' => ContactLabel::Eigen->value]],
        'created_at' => now()->subWeeks(3),
        'updated_at' => now()->subWeeks(3),
    ]);

    // Matching lead created just now, so $threeWeeksOld only gains this duplicate on refresh.
    $newMatch = Lead::factory()->create([
        'first_name' => 'Fresh',
        'last_name'  => 'Match',
        'emails'     => [['value' => 'window.dup@example.com', 'label' => ContactLabel::Relatie->value]],
    ]);

    $this->artisan('duplicates:refresh-cache')->assertSuccessful();

    $cached = $this->leadCache->getCachedDuplicates($threeWeeksOld->id);

    expect($cached)->toContain($newMatch->id);
});

// A lead created before the duplicate search window is assumed already handled and is skipped by
// the incremental refresh - its cache is left stale/empty rather than recomputed.
test('incremental refresh skips leads created before the duplicate search window', function () {
    $tooOld = Lead::factory()->create([
        'first_name' => 'Old',
        'last_name'  => 'Lead',
        'emails'     => [['value' => 'too.old.dup@example.com', 'label' => ContactLabel::Eigen->value]],
        'created_at' => now()->subWeeks(LeadRepository::DUPLICATE_SEARCH_PERIOD_WEEKS + 1),
        'updated_at' => now()->subWeeks(LeadRepository::DUPLICATE_SEARCH_PERIOD_WEEKS + 1),
    ]);

    Lead::factory()->create([
        'first_name' => 'Fresh',
        'last_name'  => 'Match',
        'emails'     => [['value' => 'too.old.dup@example.com', 'label' => ContactLabel::Relatie->value]],
    ]);

    $this->artisan('duplicates:refresh-cache')->assertSuccessful();

    // Never refreshed by the incremental job, so nothing is cached for it (see cache key prefix
    // in LeadDuplicateCacheService's constructor).
    expect(Cache::has("lead_duplicates:{$tooOld->id}"))->toBeFalse();
});
