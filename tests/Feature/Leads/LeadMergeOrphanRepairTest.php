<?php

use App\Models\Anamnesis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

uses(RefreshDatabase::class);

/**
 * Rebuild the situation the old merge left behind: audit activity on the primary, duplicate soft
 * deleted, related rows still pointing at the duplicate.
 *
 * Note that activities cannot be used as the orphan here: deleting a lead hard deletes its
 * activities (LogsActivity::deleting), which is exactly why they are unrecoverable for old merges.
 */
function recordOldMerge(Lead $primary, Lead $duplicate): void
{
    Activity::create([
        'title'   => 'System: Duplicate Lead Removed',
        'comment' => "Removed duplicate lead \"{$duplicate->name}\" (ID: {$duplicate->id}) during merge operation.",
        'type'    => 'system',
        'is_done' => 1,
        'lead_id' => $primary->id,
    ]);

    $duplicate->delete();
}

function orphanedPersonOn(Lead $lead): Person
{
    $person = Person::factory()->create();

    $lead->attachPersons([$person->id]);

    return $person;
}

test('dry run reports orphans without changing anything', function () {
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create();

    $person = orphanedPersonOn($duplicate);

    recordOldMerge($primary, $duplicate);

    $this->artisan('leads:repair-merge-orphans', ['--dry-run' => true])->assertSuccessful();

    expect(DB::table('lead_persons')->where('lead_id', $duplicate->id)->where('person_id', $person->id)->exists())
        ->toBeTrue()
        ->and(DB::table('lead_persons')->where('lead_id', $primary->id)->count())->toBe(0);
});

test('it reattaches orphaned rows to the primary lead', function () {
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create();

    $person = orphanedPersonOn($duplicate);
    $anamnesis = Anamnesis::where('lead_id', $duplicate->id)->where('person_id', $person->id)->firstOrFail();

    recordOldMerge($primary, $duplicate);

    $this->artisan('leads:repair-merge-orphans')->assertSuccessful();

    expect(DB::table('lead_persons')->where('lead_id', $primary->id)->pluck('person_id')->all())
        ->toBe([$person->id])
        ->and(DB::table('lead_persons')->where('lead_id', $duplicate->id)->count())->toBe(0)
        ->and($anamnesis->fresh()->lead_id)->toBe($primary->id);
});

test('it skips merges whose primary lead was deleted later on', function () {
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create();

    $person = orphanedPersonOn($duplicate);

    recordOldMerge($primary, $duplicate);
    $primary->delete();

    $this->artisan('leads:repair-merge-orphans')->assertSuccessful();

    expect(DB::table('lead_persons')->where('lead_id', $duplicate->id)->pluck('person_id')->all())
        ->toBe([$person->id]);
});

test('it skips duplicates that were restored afterwards', function () {
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create();

    $person = orphanedPersonOn($duplicate);

    recordOldMerge($primary, $duplicate);
    $duplicate->restore();

    $this->artisan('leads:repair-merge-orphans')->assertSuccessful();

    expect(DB::table('lead_persons')->where('lead_id', $duplicate->id)->pluck('person_id')->all())
        ->toBe([$person->id]);
});
