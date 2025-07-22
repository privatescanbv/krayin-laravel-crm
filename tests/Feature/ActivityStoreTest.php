<?php

namespace Tests\Feature;

use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

test('test fields with storing activities', function () {
    // Arrange
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'created_by'    => $user->id,
        'department_id' => 1,
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
    $response = $this->postJson(route('admin.leads.activities.store', $lead->id), $activityData);
    $response->assertStatus(200);

    // Assert
    $this->assertDatabaseHas('activities', [
        'title'   => 'Test activity',
        'comment' => 'This is a test activity description.',
    ]);
});
