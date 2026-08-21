<?php

namespace Tests\Feature;

use App\Enums\ContactLabel;
use App\Enums\DuplicateEntityType;
use App\Services\DuplicateFalsePositiveService;
use App\Services\PersonDuplicateCacheService;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();

    $this->service = app(PersonDuplicateCacheService::class);
    $this->repository = app(PersonRepository::class);
});

/**
 * The rebuild derives matches from buckets instead of querying per person; this asserts it lands on
 * the same answer as the regular detection for every person in the set.
 */
function assertIndexMatchesDetection(): void
{
    Person::all()->each(function (Person $person) {
        $expected = app(PersonRepository::class)->findPotentialDuplicates($person)->isNotEmpty();

        expect((bool) $person->fresh()->has_duplicates)
            ->toBe($expected, "has_duplicates wijkt af voor persoon {$person->id} ({$person->name})");
    });
}

test('the rebuild flags exactly the persons the detection finds', function () {
    // Shared email
    Person::factory()->create(['first_name' => 'Ann', 'last_name' => 'Mail', 'emails' => [['value' => 'shared@example.com', 'label' => ContactLabel::Eigen->value]]]);
    Person::factory()->create(['first_name' => 'Bob', 'last_name' => 'Mail', 'emails' => [['value' => 'shared@example.com', 'label' => ContactLabel::Relatie->value]]]);

    // Shared phone
    Person::factory()->create(['first_name' => 'Cor', 'last_name' => 'Fone', 'phones' => [['value' => '+31612345678', 'label' => ContactLabel::Eigen->value]]]);
    Person::factory()->create(['first_name' => 'Dia', 'last_name' => 'Fone', 'phones' => [['value' => '+31612345678', 'label' => ContactLabel::Eigen->value]]]);

    // Same first + last name
    Person::factory()->create(['first_name' => 'Eva', 'last_name' => 'Naam']);
    Person::factory()->create(['first_name' => 'Eva', 'last_name' => 'Naam']);

    // Married name crossing a birth name
    Person::factory()->create(['first_name' => 'Marie', 'last_name' => 'Vries']);
    Person::factory()->create(['first_name' => 'Marie', 'last_name' => 'Jansen', 'married_name' => 'Vries']);

    // No match at all
    Person::factory()->create(['first_name' => 'Solo', 'last_name' => 'Uniek']);

    $result = $this->service->rebuildHasDuplicatesIndex();

    expect($result['processed'])->toBe(Person::count())
        ->and($result['flagged'])->toBe(8)
        ->and($result['turned_on'])->toBe(8);

    assertIndexMatchesDetection();
});

test('the rebuild leaves a false positive pair unflagged', function () {
    $a = Person::factory()->create(['first_name' => 'Fien', 'last_name' => 'Falsepos', 'emails' => [['value' => 'fp@example.com', 'label' => ContactLabel::Eigen->value]]]);
    $b = Person::factory()->create(['first_name' => 'Gijs', 'last_name' => 'Falsepos', 'emails' => [['value' => 'fp@example.com', 'label' => ContactLabel::Eigen->value]]]);

    app(DuplicateFalsePositiveService::class)->storeForEntities(DuplicateEntityType::PERSON, [$a->id, $b->id]);

    $this->service->rebuildHasDuplicatesIndex();

    expect((bool) $a->fresh()->has_duplicates)->toBeFalse()
        ->and((bool) $b->fresh()->has_duplicates)->toBeFalse();

    assertIndexMatchesDetection();
});

test('the rebuild clears flags that no longer apply', function () {
    $person = Person::factory()->create(['first_name' => 'Stale', 'last_name' => 'Vlag']);
    $person->forceFill(['has_duplicates' => true])->save();

    $trashed = Person::factory()->create(['first_name' => 'Weg', 'last_name' => 'Gegooid']);
    $trashed->forceFill(['has_duplicates' => true])->save();
    $trashed->delete();

    $result = $this->service->rebuildHasDuplicatesIndex();

    expect($result['turned_off'])->toBe(2)
        ->and((bool) $person->fresh()->has_duplicates)->toBeFalse()
        ->and((bool) Person::withTrashed()->find($trashed->id)->has_duplicates)->toBeFalse();
});
