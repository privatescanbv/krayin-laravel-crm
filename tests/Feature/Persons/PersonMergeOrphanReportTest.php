<?php

use App\Models\Clinic;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopPerson;
use App\Models\PatientNotification;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\Group;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();
    Activity::unsetEventDispatcher();
});

/**
 * Rebuild the situation an old merge left behind: audit activity on the primary, duplicate soft
 * deleted, related rows still pointing at the duplicate.
 */
function recordOldPersonMerge(Person $primary, Person $duplicate): void
{
    Activity::create([
        'title'     => 'System: Duplicate Person Removed',
        'comment'   => "Removed duplicate person \"{$duplicate->name}\" (ID: {$duplicate->id}) during merge operation.",
        'type'      => 'system',
        'is_done'   => 1,
        'person_id' => $primary->id,
    ]);

    $duplicate->delete();
}

test('report command lists orphans without changing anything', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    $notification = PatientNotification::factory()->create(['patient_id' => $duplicate->id]);

    recordOldPersonMerge($primary, $duplicate);

    $this->artisan('persons:report-merge-orphans')->assertSuccessful();

    expect($notification->fresh()->patient_id)->toBe($duplicate->id)
        ->and(DB::table('patient_notifications')->where('patient_id', $primary->id)->count())->toBe(0);
});

test('report command can filter to a specific duplicate person', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $otherPrimary = Person::factory()->create();
    $otherDuplicate = Person::factory()->create();

    PatientNotification::factory()->create(['patient_id' => $duplicate->id]);
    PatientNotification::factory()->create(['patient_id' => $otherDuplicate->id]);

    recordOldPersonMerge($primary, $duplicate);
    recordOldPersonMerge($otherPrimary, $otherDuplicate);

    $this->artisan('persons:report-merge-orphans', ['--person' => [$duplicate->id]])
        ->assertSuccessful()
        ->expectsOutputToContain((string) $duplicate->id);
});

test('report command skips duplicates that were restored afterwards', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    PatientNotification::factory()->create(['patient_id' => $duplicate->id]);

    recordOldPersonMerge($primary, $duplicate);
    $duplicate->restore();

    $this->artisan('persons:report-merge-orphans')
        ->assertSuccessful()
        ->expectsOutputToContain('No merged duplicate persons found.');
});

test('report command does not treat skipped duplicate activities as orphans', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $group = Group::firstOrFail();

    $activityData = fn (int $personId) => [
        'type'      => 'note',
        'title'     => 'Gebeld met patiënt',
        'status'    => 'active',
        'group_id'  => $group->id,
        'person_id' => $personId,
    ];

    Activity::query()->create($activityData($primary->id));
    Activity::query()->create($activityData($duplicate->id));

    recordOldPersonMerge($primary, $duplicate);

    $this->artisan('persons:report-merge-orphans')
        ->assertSuccessful()
        ->expectsOutputToContain('geen weesrijen')
        ->doesntExpectOutputToContain('activities:');
});

test('report command still lists unique activities left on a duplicate', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $group = Group::firstOrFail();

    Activity::query()->create([
        'type'      => 'note',
        'title'     => 'Alleen op duplicaat',
        'status'    => 'active',
        'group_id'  => $group->id,
        'person_id' => $duplicate->id,
    ]);

    recordOldPersonMerge($primary, $duplicate);

    $this->artisan('persons:report-merge-orphans')
        ->assertSuccessful()
        ->expectsOutputToContain('activities: 1');
});

test('report command lists leftover inkoop_persons crm_id links', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    $clinic = Clinic::factory()->create();
    $invoice = InkoopInvoice::create([
        'clinic_id' => $clinic->id,
        'pdf_path'  => 'test/orphan.pdf',
    ]);

    InkoopPerson::create([
        'clinic_id'  => $clinic->id,
        'invoice_id' => $invoice->id,
        'firstname'  => 'Wees',
        'lastname'   => 'Link',
        'crm_id'     => (string) $duplicate->id,
    ]);

    recordOldPersonMerge($primary, $duplicate);

    $this->artisan('persons:report-merge-orphans')
        ->assertSuccessful()
        ->expectsOutputToContain('inkoop_persons: 1');
});
