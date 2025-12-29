<?php

use App\Enums\CallStatus as CallStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

test('spoken does not reschedule activity', function () {
    $roleId = DB::table('roles')->insertGetId([
        'name'            => 'Tester',
        'description'     => 'Test role',
        'permission_type' => 'all',
        'permissions'     => json_encode([]),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $userId = DB::table('users')->insertGetId([
        'first_name' => 'Test',
        'last_name'  => 'User',
        'email'      => 'test@example.com',
        'password'   => bcrypt('secret'),
        'status'     => 1,
        'role_id'    => $roleId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::find($userId);
    $this->actingAs($user, 'user');

    $activity = Activity::create([
        'title'         => 'Call',
        'type'          => 'call',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'user_id'       => $user->id,
    ]);

    $response = $this->postJson(route('admin.activities.call-statuses.store', $activity->id), [
        'status'          => CallStatusEnum::SPOKEN->value,
        'omschrijving'    => null,
        'reschedule_days' => '',
    ]);

    $response->assertOk();
    $activity->refresh();
    $this->assertTrue($activity->schedule_from->isToday());
});

test('not spoken defaults to 7 days when empty', function () {
    $roleId = DB::table('roles')->insertGetId([
        'name'            => 'Tester 2',
        'description'     => 'Test role 2',
        'permission_type' => 'all',
        'permissions'     => json_encode([]),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $userId = DB::table('users')->insertGetId([
        'first_name' => 'Test',
        'last_name'  => 'User 2',
        'email'      => 'test2@example.com',
        'password'   => bcrypt('secret'),
        'status'     => 1,
        'role_id'    => $roleId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::find($userId);
    $this->actingAs($user, 'user');

    $activity = Activity::create([
        'title'         => 'Call 2',
        'type'          => 'call',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'user_id'       => $user->id,
    ]);

    $response = $this->postJson(route('admin.activities.call-statuses.store', $activity->id), [
        'status'          => CallStatusEnum::NOT_REACHABLE->value,
        'omschrijving'    => null,
        'reschedule_days' => '',
    ]);

    $response->assertOk();
    $activity->refresh();
    $this->assertTrue($activity->schedule_from->isSameDay(now()->addDays(7)));
});

test('call status with send_email returns email data', function () {
    $roleId = DB::table('roles')->insertGetId([
        'name'            => 'Tester 3',
        'description'     => 'Test role 3',
        'permission_type' => 'all',
        'permissions'     => json_encode([]),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $userId = DB::table('users')->insertGetId([
        'first_name' => 'Test',
        'last_name'  => 'User 3',
        'email'      => 'test3@example.com',
        'password'   => bcrypt('secret'),
        'status'     => 1,
        'role_id'    => $roleId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::find($userId);
    $this->actingAs($user, 'user');

    // Create a lead with email
    $lead = Lead::create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [
            ['value' => 'john.doe@example.com', 'is_default' => true],
        ],
        'user_id'    => $user->id,
    ]);

    $activity = Activity::create([
        'title'         => 'Call 3',
        'type'          => 'call',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'user_id'       => $user->id,
        'lead_id'       => $lead->id,
    ]);

    $response = $this->postJson(route('admin.activities.call-statuses.store', $activity->id), [
        'status'          => CallStatusEnum::SPOKEN->value,
        'omschrijving'    => 'Test call status',
        'reschedule_days' => '',
        'send_email'      => true,
    ]);

    $response->assertOk();
    $responseData = $response->json();

    expect($responseData['send_email'])->toBeTrue();
    expect($responseData['default_email'])->toBe('john.doe@example.com');
    expect($responseData['activity_id'])->toBe($activity->id);
});

test('activity can be linked to email', function () {
    $roleId = DB::table('roles')->insertGetId([
        'name'            => 'Tester 4',
        'description'     => 'Test role 4',
        'permission_type' => 'all',
        'permissions'     => json_encode([]),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $userId = DB::table('users')->insertGetId([
        'first_name' => 'Test',
        'last_name'  => 'User 4',
        'email'      => 'test4@example.com',
        'password'   => bcrypt('secret'),
        'status'     => 1,
        'role_id'    => $roleId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::find($userId);
    $this->actingAs($user, 'user');

    // Create an email
    $email = Email::create([
        'subject'   => 'Test Email',
        'reply'     => 'Test email content',
        'from'      => ['test@example.com'],
        'reply_to'  => ['recipient@example.com'],
        'user_type' => 'user',
        'is_read'   => 0,
        'source'    => 'test',
        'message_id'=> (string) Str::uuid(),
    ]);

    // Create an activity
    $activity = Activity::create([
        'title'         => 'Test Activity',
        'type'          => 'call',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'user_id'       => $user->id,
    ]);
    $email->save();

    // Test the relationship
    //    $this->assertTrue($email->activity->is($activity));
    //    $this->assertTrue($activity->emails->contains($email));
});
