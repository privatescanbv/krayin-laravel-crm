<?php

namespace Tests\Feature;

use App\Enums\Departments;
use App\Models\Department;
use Database\Seeders\TestSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Group;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
});

test('test fields with storing activities', function () {
    // Arrange
    $user = User::factory()->create();
    $department = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $lead = Lead::factory()->create([
        'created_by'    => $user->id,
        'department_id' => $department->id,
    ]);
    $this->actingAs($user, 'user');

    // Get the group that should be auto-selected based on lead's department
    $expectedGroup = Group::where('department_id', $department->id)->firstOrFail();

    $activityData = [
        'title'         => 'Test activity',
        'description'   => 'This is a test activity description.',
        'type'          => 'task',
        'schedule_to'   => now()->addHour()->format('Y-m-d H:i:s'),
        // Don't provide group_id - should be auto-determined from lead's department
    ];

    // Act
    $response = test()->withHeaders([
        'X-API-KEY' => 'valid-api-key-123',
    ])->postJson(route('admin.leads.activities.store', $lead->id), $activityData);
    $response->assertStatus(200);

    // Assert
    $this->assertDatabaseHas('activities', [
        'title'         => 'Test activity',
        'comment'       => 'This is a test activity description.',
        'group_id'      => $expectedGroup->id, // Should be auto-determined from lead's department
        'lead_id'       => $lead->id,
        'schedule_from' => null,
    ]);
});

test('reject duplicate open activity by same title on same lead', function () {
    // Arrange
    $user = User::factory()->create();
    $department = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $lead = Lead::factory()->create([
        'created_by'    => $user->id,
        'department_id' => $department->id,
    ]);
    $this->actingAs($user, 'user');

    $activityPayload = [
        'title'         => 'Bel klant terug',
        'description'   => 'Eerste poging',
        'type'          => 'task',
        'schedule_from' => now()->format('Y-m-d H:i:s'),
        'schedule_to'   => now()->addHour()->format('Y-m-d H:i:s'),
    ];

    // Create first activity (should succeed)
    $first = test()->withHeaders([
        'X-API-KEY' => 'valid-api-key-123',
    ])->postJson(route('admin.leads.activities.store', $lead->id), $activityPayload);
    $first->assertStatus(200);

    // Attempt duplicate with same title for same lead while is_done = 0
    $duplicate = test()->withHeaders([
        'X-API-KEY' => 'valid-api-key-123',
    ])->postJson(route('admin.leads.activities.store', $lead->id), array_merge($activityPayload, [
        'description' => 'Tweede poging zelfde titel',
    ]));

    $duplicate->assertStatus(409);
    $duplicate->assertJsonStructure(['message', 'errors' => ['title']]);
});

test('accept activity when provided group_id matches lead department', function () {
    // Arrange
    $user = User::factory()->create();
    $privatescanDepartment = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();

    $lead = Lead::factory()->create([
        'created_by'    => $user->id,
        'department_id' => $privatescanDepartment->id,
    ]);
    $this->actingAs($user, 'user');

    // Pick a group from the same department
    $matchingGroup = Group::where('department_id', $privatescanDepartment->id)->firstOrFail();

    $payload = [
        'title'         => 'Matching Group Activity',
        'description'   => 'Should be accepted with matching group/department',
        'type'          => 'task',
        'schedule_from' => now()->format('Y-m-d H:i:s'),
        'schedule_to'   => now()->addHour()->format('Y-m-d H:i:s'),
        'group_id'      => $matchingGroup->id,
    ];

    // Act
    $response = test()->withHeaders([
        'X-API-KEY' => 'valid-api-key-123',
    ])->postJson(route('admin.leads.activities.store', $lead->id), $payload);

    // Assert
    $response->assertStatus(200);
    $this->assertDatabaseHas('activities', [
        'title'         => 'Matching Group Activity',
        'group_id'      => $matchingGroup->id,
        'lead_id'       => $lead->id,
        'schedule_from' => null,
    ]);
});

test('edit form and update only expose managed deadline', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $group = Group::query()->firstOrFail();
    $originalScheduleFrom = now()->subDays(5)->startOfMinute();
    $newScheduleTo = now()->addDays(3)->startOfMinute();

    $activity = Activity::create([
        'title'         => 'Deadline only activity',
        'type'          => 'task',
        'schedule_from' => $originalScheduleFrom,
        'schedule_to'   => now()->addDay()->startOfMinute(),
        'group_id'      => $group->id,
        'user_id'       => $user->id,
        'is_done'       => 0,
    ]);

    $this->get(route('admin.activities.edit', $activity->id))
        ->assertOk()
        ->assertDontSee('name="schedule_from"', false)
        ->assertSee('name="schedule_to"', false);

    $response = $this->put(route('admin.activities.update', $activity->id), [
        'title'         => $activity->title,
        'type'          => 'task',
        'schedule_from' => now()->addYear()->format('Y-m-d H:i:s'),
        'schedule_to'   => $newScheduleTo->format('Y-m-d H:i:s'),
        'group_id'      => $group->id,
        'user_id'       => $user->id,
    ]);

    $response->assertRedirect(route('admin.activities.index'));

    $activity->refresh();

    expect($activity->schedule_from->equalTo($originalScheduleFrom))->toBeTrue()
        ->and($activity->schedule_to->equalTo($newScheduleTo))->toBeTrue();
});
