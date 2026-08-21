<?php

use App\Enums\ContactLabel;
use App\Services\PersonDuplicateCacheService;
use Webkul\Contact\Models\Person;

test('editing a person so it no longer matches clears its own has_duplicates flag immediately', function () {
    $a = Person::factory()->create([
        'emails' => [['value' => 'ratchet.dup@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);
    $b = Person::factory()->create([
        'emails' => [['value' => 'ratchet.dup@example.com', 'label' => ContactLabel::Eigen->value]],
    ]);

    $service = app(PersonDuplicateCacheService::class);

    // Real duplicate pair: scanning A finds B, both get flagged true.
    $service->getCachedDuplicates($a->id);

    expect($a->refresh()->has_duplicates)->toBeTrue()
        ->and($b->refresh()->has_duplicates)->toBeTrue()
        ->and($service->countPersonsWithDuplicates())->toBe(2);

    // B is edited so it no longer matches anything. Nobody explicitly rescans B - this
    // relies on PersonObserver eagerly refreshing the duplicate cache on save, instead of
    // just invalidating it and waiting for someone to happen to view B again.
    $b->update(['emails' => [['value' => 'no-longer-matching@example.com', 'label' => ContactLabel::Eigen->value]]]);

    expect($b->refresh()->has_duplicates)->toBeFalse();

    // A hasn't been touched yet, so it's still stale until it's looked at again - that's
    // the accepted ceiling here, closed within 24h by the `duplicates:refresh-cache --index`
    // cron, or immediately the next time A itself is viewed/scanned (below).
    $service->invalidatePersonCache($a->id);
    $service->getCachedDuplicates($a->id);

    expect($a->refresh()->has_duplicates)->toBeFalse()
        ->and($service->countPersonsWithDuplicates())->toBe(0);
});
