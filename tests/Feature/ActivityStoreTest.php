<?php

namespace Tests\Feature;

use App\Enums\Departments;
use App\Models\Department;
use Database\Seeders\TestSeeder;
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
        'schedule_from' => now()->format('Y-m-d H:i:s'),
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
        'title'    => 'Test activity',
        'comment'  => 'This is a test activity description.',
        'group_id' => $expectedGroup->id, // Should be auto-determined from lead's department
        'lead_id'  => $lead->id,
    ]);
});
