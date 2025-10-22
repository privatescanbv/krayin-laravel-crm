<?php

use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Group;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    // Disable installer redirect
    test()->withoutMiddleware(CanInstall::class);
    config(['api.keys' => ['valid-api-key-123']]);
});

test('person activities index includes email from activity without person_id', function () {
    // Arrange: create admin user
    $adminRole = Role::factory()->create(['permission_type' => 'all']);
    $admin = User::factory()->create(['status' => 1, 'role_id' => $adminRole->id]);

    $group = Group::firstOrCreate(['name' => 'Default Group']);

    // Create a person and a lead linked to that person
    $person = Person::factory()->create();
    DB::table('lead_persons')->insert([
        'lead_id'   => ($lead = Lead::factory()->create())->id,
        'person_id' => $person->id,
    ]);

    // Create an activity for the lead, and link that activity to the person via pivot
    $activity = Activity::create([
        'type'          => 'task',
        'title'         => 'Follow up',
        'group_id'      => $group->id,
        'lead_id'       => $lead->id,
        'schedule_from' => now()->format('Y-m-d H:i:s'),
        'schedule_to'   => now()->addHour()->format('Y-m-d H:i:s'),
        'is_done'       => 0,
    ]);
    DB::table('person_activities')->insert([
        'person_id'   => $person->id,
        'activity_id' => $activity->id,
    ]);

    // Create inbox folder first
    $folder = \Webkul\Email\Models\Folder::create(['name' => 'inbox']);

    // Email that belongs to the above activity, does not have person_id, but does have lead_id and activity_id
    $email = Email::create([
        'subject'     => 'Re: Follow up',
        'is_read'     => 0,
        'folder_id'   => $folder->id,
        'from'        => json_encode(['test@example.com']),
        'reply_to'    => json_encode(['test@example.com']),
        'cc'          => json_encode([]),
        'bcc'         => json_encode([]),
        'reply'       => 'Body',
        'lead_id'     => $lead->id,
        'activity_id' => $activity->id,
    ]);

    // Act: call the person activities endpoint
    $this->actingAs($admin, 'user');
    $response = $this->getJson(route('admin.contacts.persons.activities.index', $person->id));

    // Assert: response ok and includes an email-type activity referencing our email
    $response->assertOk();
    $payload = $response->json('data');
    $hasEmailActivity = collect($payload)->contains(function ($item) use ($email) {
        return ($item['type'] ?? null) === 'email' && ($item['id'] ?? null) === $email->id;
    });

    $this->assertTrue($hasEmailActivity, 'Expected email activity to be present in person activities index');
});
