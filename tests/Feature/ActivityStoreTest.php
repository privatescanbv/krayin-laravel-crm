<?php

namespace Tests\Feature;

use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
});

test('test fields with storing activities', function () {
    // Arrange
    $user = User::factory()->create();
    $department = \App\Models\Department::where('name', \App\Enums\Departments::PRIVATESCAN->value)->firstOrFail();
    $lead = Lead::factory()->create([
        'created_by'    => $user->id,
        'department_id' => $department->id,
    ]);
    $this->actingAs($user, 'user');

    $activityData = [
        'title'         => 'Test activity',
        'description'   => 'This is a test activity description.',
        'type'          => 'task',
        'schedule_from' => now()->format('Y-m-d H:i:s'),
        'schedule_to'   => now()->addHour()->format('Y-m-d H:i:s'),
    ];

    // Act
    $response = test()->withHeaders([
        'X-API-KEY' => 'valid-api-key-123',
    ])->postJson(route('admin.leads.activities.store', $lead->id), $activityData);
    $response->assertStatus(200);

    // Assert
    $this->assertDatabaseHas('activities', [
        'title'   => 'Test activity',
        'comment' => 'This is a test activity description.',
    ]);
});
