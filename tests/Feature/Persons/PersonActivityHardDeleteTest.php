<?php

use Database\Seeders\TestSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\Group;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('soft deleting a person keeps its person_id activities', function () {
    $person = Person::factory()->create();
    $group = Group::firstOrFail();

    $activity = Activity::query()->create([
        'type'          => 'note',
        'title'         => 'Keep on soft delete',
        'group_id'      => $group->id,
        'person_id'     => $person->id,
        'schedule_from' => now(),
        'schedule_to'   => now()->addHour(),
        'is_done'       => true,
    ]);

    $person->delete();

    expect(Activity::find($activity->id))->not->toBeNull()
        ->and($activity->fresh()->person_id)->toBe($person->id);
});

test('force deleting a person deletes its person_id activities', function () {
    $person = Person::factory()->create();
    $group = Group::firstOrFail();

    $activity = Activity::query()->create([
        'type'          => 'note',
        'title'         => 'Wipe on hard delete',
        'group_id'      => $group->id,
        'person_id'     => $person->id,
        'schedule_from' => now(),
        'schedule_to'   => now()->addHour(),
        'is_done'       => true,
    ]);

    $person->forceDelete();

    expect(Activity::find($activity->id))->toBeNull();
});
