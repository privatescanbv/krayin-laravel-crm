<?php

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Activity\Models\Activity;
use Webkul\User\Models\Group;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

function makeTypeChangeActivity(array $attributes = []): Activity
{
    $group = Group::create([
        'name'        => 'Type change test group',
        'description' => 'Type change test group',
    ]);

    return Activity::create(array_merge([
        'title'       => 'Type change test',
        'type'        => ActivityType::TASK->value,
        'schedule_to' => now()->addDay(),
        'group_id'    => $group->id,
        'is_done'     => false,
        'status'      => ActivityStatus::ACTIVE,
    ], $attributes));
}

test('can change activity type from task to call', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $activity = makeTypeChangeActivity(['user_id' => $user->id]);

    $response = $this->put(route('admin.activities.update', $activity->id), [
        'title'       => $activity->title,
        'type'        => ActivityType::CALL->value,
        'schedule_to' => now()->addDay()->format('Y-m-d\TH:i'),
        'group_id'    => $activity->group_id,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('activities', [
        'id'   => $activity->id,
        'type' => ActivityType::CALL->value,
    ]);
});

test('can change activity type from call to task', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $activity = makeTypeChangeActivity([
        'user_id' => $user->id,
        'type'    => ActivityType::CALL->value,
    ]);

    $response = $this->put(route('admin.activities.update', $activity->id), [
        'title'       => $activity->title,
        'type'        => ActivityType::TASK->value,
        'schedule_to' => now()->addDay()->format('Y-m-d\TH:i'),
        'group_id'    => $activity->group_id,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('activities', [
        'id'   => $activity->id,
        'type' => ActivityType::TASK->value,
    ]);
});

test('cannot change non-user-selectable activity type', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $activity = makeTypeChangeActivity([
        'user_id' => $user->id,
        'type'    => ActivityType::FILE->value,
        'is_done' => true,
    ]);

    // FILE activities are read-only (is_done = true), so update is rejected
    $response = $this->put(route('admin.activities.update', $activity->id), [
        'title'    => $activity->title,
        'type'     => ActivityType::TASK->value,
        'group_id' => $activity->group_id,
    ]);

    // Should be rejected because activity is done
    $this->assertDatabaseHas('activities', [
        'id'   => $activity->id,
        'type' => ActivityType::FILE->value,
    ]);
});

test('cannot change task type to non-user-selectable type', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $activity = makeTypeChangeActivity(['user_id' => $user->id]);

    $response = $this->put(route('admin.activities.update', $activity->id), [
        'title'       => $activity->title,
        'type'        => ActivityType::FILE->value,
        'schedule_to' => now()->addDay()->format('Y-m-d\TH:i'),
        'group_id'    => $activity->group_id,
    ]);

    $response->assertRedirect();

    // Type should remain unchanged (FILE is not a valid user-selectable type)
    $this->assertDatabaseHas('activities', [
        'id'   => $activity->id,
        'type' => ActivityType::TASK->value,
    ]);
});
