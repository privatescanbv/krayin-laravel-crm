<?php

use App\Enums\ActivityActionType;
use App\Enums\CallStatus as CallStatusEnum;
use Carbon\Carbon;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\User\Models\Group;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

function createActivityActionTestUser(): User
{
    $roleId = DB::table('roles')->insertGetId([
        'name'            => 'Activity action tester',
        'description'     => 'Test role',
        'permission_type' => 'all',
        'permissions'     => json_encode([]),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $userId = DB::table('users')->insertGetId([
        'first_name' => 'Test',
        'last_name'  => 'User',
        'email'      => 'activity-action-test@example.com',
        'password'   => bcrypt('secret'),
        'status'     => 1,
        'role_id'    => $roleId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return User::findOrFail($userId);
}

test('belstatus reschedule only moves deadline and keeps schedule_from before deadline', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-20 17:14:00'));

    $user = createActivityActionTestUser();
    $this->actingAs($user, 'user');

    $originalFrom = Carbon::parse('2026-05-19 08:00:00');
    $originalTo = Carbon::parse('2026-05-19 11:14:00');

    $activity = Activity::create([
        'title'         => 'Nieuwe lead, bellen',
        'type'          => 'call',
        'schedule_from' => $originalFrom,
        'schedule_to'   => $originalTo,
        'user_id'       => $user->id,
    ]);

    $response = $this->postJson(route('admin.activities.actions.store', $activity->id), [
        'type'            => ActivityActionType::Belstatus->value,
        'call_status'     => CallStatusEnum::NOT_REACHABLE->value,
        'body'            => 'Doorgezet naar vandaag',
        'reschedule_days' => 1,
    ]);

    $response->assertOk();
    $activity->refresh();

    expect($activity->schedule_to->equalTo(Carbon::parse('2026-05-21 17:14:00')))->toBeTrue()
        ->and($activity->schedule_from->equalTo($originalFrom))->toBeTrue()
        ->and($activity->schedule_from->lte($activity->schedule_to))->toBeTrue();

    Carbon::setTestNow();
});

test('belstatus reschedule clamps legacy schedule_from when it would exceed new deadline', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-20 17:14:00'));

    $user = createActivityActionTestUser();
    $this->actingAs($user, 'user');

    $activity = Activity::create([
        'title'         => 'Nieuwe lead, bellen',
        'type'          => 'call',
        'schedule_from' => Carbon::parse('2026-05-20 17:14:00'),
        'schedule_to'   => Carbon::parse('2026-05-19 11:14:00'),
        'user_id'       => $user->id,
    ]);

    $response = $this->postJson(route('admin.activities.actions.store', $activity->id), [
        'type'            => ActivityActionType::Belstatus->value,
        'call_status'     => CallStatusEnum::NOT_REACHABLE->value,
        'reschedule_days' => 1,
    ]);

    $response->assertOk();
    $activity->refresh();

    expect($activity->schedule_to->equalTo(Carbon::parse('2026-05-21 17:14:00')))->toBeTrue()
        ->and($activity->schedule_from->equalTo(Carbon::parse('2026-05-20 17:14:00')))->toBeTrue()
        ->and($activity->schedule_from->lte($activity->schedule_to))->toBeTrue();

    Carbon::setTestNow();
});

test('manual deadline update clamps legacy schedule_from when it would exceed deadline', function () {
    $user = createActivityActionTestUser();
    $this->actingAs($user, 'user');

    $group = Group::query()->firstOrFail();
    $newScheduleTo = now()->addDays(3)->startOfMinute();

    $activity = Activity::create([
        'title'         => 'Deadline only activity',
        'type'          => 'task',
        'schedule_from' => now()->addDays(5)->startOfMinute(),
        'schedule_to'   => now()->subDay()->startOfMinute(),
        'group_id'      => $group->id,
        'user_id'       => $user->id,
        'is_done'       => 0,
    ]);

    $response = $this->put(route('admin.activities.update', $activity->id), [
        'title'       => $activity->title,
        'type'        => 'task',
        'schedule_to' => $newScheduleTo->format('Y-m-d H:i:s'),
        'group_id'    => $group->id,
        'user_id'     => $user->id,
    ]);

    $response->assertRedirect(route('admin.activities.index'));

    $activity->refresh();

    expect($activity->schedule_to->equalTo($newScheduleTo))->toBeTrue()
        ->and($activity->schedule_from->equalTo($newScheduleTo))->toBeTrue();
});
