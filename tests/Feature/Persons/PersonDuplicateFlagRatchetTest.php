<?php

use App\Enums\ContactLabel;
use App\Services\PersonDuplicateCacheService;
use Webkul\Contact\Models\Person;

test('editing a person so it no longer matches clears both its own and its counterpart flag immediately', function () {
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

    // Only B is edited so it no longer matches anything. PersonObserver recomputes B *and* the
    // counterparts of B's pre-edit identity, so A is cleared right away instead of ratcheting on
    // until the hourly index rebuild.
    $unrelatedEmail = [['value' => 'no-longer-matching@example.com', 'label' => ContactLabel::Eigen->value]];
    $b->update(['emails' => $unrelatedEmail]);

    expect($b->refresh()->has_duplicates)->toBeFalse()
        ->and($a->refresh()->has_duplicates)->toBeFalse()
        ->and($service->countPersonsWithDuplicates())->toBe(0);
});
