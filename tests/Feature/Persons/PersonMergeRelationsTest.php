<?php

namespace Tests\Feature\Persons;

use App\Models\Anamnesis;
use App\Models\PatientMessage;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Group;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();
    Activity::unsetEventDispatcher();
    $this->personRepository = app(PersonRepository::class);
});

test('merging persons transfers lead_persons pivot to primary person', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $lead = Lead::factory()->create();

    DB::table('lead_persons')->insert([
        'lead_id'   => $lead->id,
        'person_id' => $duplicate->id,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(DB::table('lead_persons')->where('lead_id', $lead->id)->where('person_id', $primary->id)->exists())->toBeTrue();
    expect(DB::table('lead_persons')->where('lead_id', $lead->id)->where('person_id', $duplicate->id)->exists())->toBeFalse();
    expect(Person::withTrashed()->find($duplicate->id)->trashed())->toBeTrue();
});

test('merging persons transfers saleslead_persons pivot to primary person', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $salesLead = SalesLead::factory()->create();

    DB::table('saleslead_persons')->insert([
        'saleslead_id' => $salesLead->id,
        'person_id'    => $duplicate->id,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(DB::table('saleslead_persons')->where('saleslead_id', $salesLead->id)->where('person_id', $primary->id)->exists())->toBeTrue();
    expect(DB::table('saleslead_persons')->where('saleslead_id', $salesLead->id)->where('person_id', $duplicate->id)->exists())->toBeFalse();
});

test('merging persons transfers contact_person_id on leads', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $lead = Lead::factory()->create(['contact_person_id' => $duplicate->id]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($lead->fresh()->contact_person_id)->toBe($primary->id);
});

test('merging persons transfers contact_person_id on salesleads', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $salesLead = SalesLead::factory()->create(['contact_person_id' => $duplicate->id]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($salesLead->fresh()->contact_person_id)->toBe($primary->id);
});

test('merging persons does not create duplicate lead_persons when both already linked', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $lead = Lead::factory()->create();

    DB::table('lead_persons')->insert(['lead_id' => $lead->id, 'person_id' => $primary->id]);
    DB::table('lead_persons')->insert(['lead_id' => $lead->id, 'person_id' => $duplicate->id]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    $count = DB::table('lead_persons')->where('lead_id', $lead->id)->where('person_id', $primary->id)->count();
    expect($count)->toBe(1);
});

test('merging persons transfers anamnesis rows to primary person', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $lead = Lead::factory()->create();

    $anamnesis = Anamnesis::factory()->create([
        'lead_id'   => $lead->id,
        'person_id' => $duplicate->id,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($anamnesis->fresh()->person_id)->toBe($primary->id);
});

test('merging persons keeps newest anamnesis when primary and duplicate share the same lead', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $lead = Lead::factory()->create();

    $olderOnPrimary = Anamnesis::factory()->create([
        'lead_id'    => $lead->id,
        'person_id'  => $primary->id,
        'updated_at' => now()->subDays(2),
    ]);

    $newerOnDuplicate = Anamnesis::factory()->create([
        'lead_id'    => $lead->id,
        'person_id'  => $duplicate->id,
        'updated_at' => now(),
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(Anamnesis::query()->where('lead_id', $lead->id)->where('person_id', $primary->id)->count())->toBe(1);
    expect(Anamnesis::query()->find($olderOnPrimary->id))->toBeNull();
    expect($newerOnDuplicate->fresh()->person_id)->toBe($primary->id);
});

test('merging persons keeps newest anamnesis when primary and duplicate share the same sales lead', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $salesLead = SalesLead::factory()->create();

    $olderOnPrimary = Anamnesis::factory()->create([
        'lead_id'   => null,
        'sales_id'  => $salesLead->id,
        'person_id' => $primary->id,
        'updated_at'=> now()->subDay(),
    ]);

    $newerOnDuplicate = Anamnesis::factory()->create([
        'lead_id'   => null,
        'sales_id'  => $salesLead->id,
        'person_id' => $duplicate->id,
        'updated_at'=> now(),
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(Anamnesis::query()->where('sales_id', $salesLead->id)->where('person_id', $primary->id)->count())->toBe(1);
    expect(Anamnesis::query()->find($olderOnPrimary->id))->toBeNull();
    expect($newerOnDuplicate->fresh()->person_id)->toBe($primary->id);
});

test('merging persons transfers patient messages to primary person', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    $message = PatientMessage::factory()->create(['person_id' => $duplicate->id]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($message->fresh()->person_id)->toBe($primary->id);
});

test('merging persons transfers activities with person_id to primary person', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $group = Group::firstOrFail();

    $activity = Activity::query()->create([
        'type'          => 'note',
        'title'         => 'Direct person activity',
        'group_id'      => $group->id,
        'person_id'     => $duplicate->id,
        'schedule_from' => now(),
        'schedule_to'   => now()->addHour(),
        'is_done'       => false,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($activity->fresh()->person_id)->toBe($primary->id);
});

test('merging persons transfers email rows to primary person', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    $folder = Folder::firstOrCreate(['name' => EmailFolderEnum::INBOX->getFolderName()]);

    $email = Email::create([
        'subject'    => 'Merge test',
        'message_id' => (string) Str::uuid(),
        'source'     => 'system',
        'user_type'  => 'user',
        'is_read'    => 0,
        'folder_id'  => $folder->id,
        'person_id'  => $duplicate->id,
        'reply'      => 'Body',
        'from'       => json_encode(['merge@example.com']),
        'reply_to'   => json_encode(['merge@example.com']),
        'cc'         => json_encode([]),
        'bcc'        => json_encode([]),
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($email->fresh()->person_id)->toBe($primary->id);
});

test('merging persons transfers person_tags without duplicating tag links', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $user = User::factory()->create();

    $tagId = DB::table('tags')->insertGetId([
        'name'       => 'merge-test-tag',
        'color'      => '#000000',
        'user_id'    => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('person_tags')->insert([
        ['tag_id' => $tagId, 'person_id' => $primary->id],
        ['tag_id' => $tagId, 'person_id' => $duplicate->id],
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    $count = DB::table('person_tags')->where('tag_id', $tagId)->where('person_id', $primary->id)->count();
    expect($count)->toBe(1);
});

test('merging persons transfers activity_portal_persons without duplicate activity links', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $group = Group::firstOrFail();

    $activity = Activity::query()->create([
        'type'          => 'note',
        'title'         => 'Portal activity',
        'group_id'      => $group->id,
        'schedule_from' => now(),
        'schedule_to'   => now()->addHour(),
        'is_done'       => false,
    ]);

    DB::table('activity_portal_persons')->insert([
        ['activity_id' => $activity->id, 'person_id' => $primary->id, 'created_at' => now(), 'updated_at' => now()],
        ['activity_id' => $activity->id, 'person_id' => $duplicate->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    $count = DB::table('activity_portal_persons')->where('activity_id', $activity->id)->where('person_id', $primary->id)->count();
    expect($count)->toBe(1);
});
