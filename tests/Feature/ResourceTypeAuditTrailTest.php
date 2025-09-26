<?php

use App\Models\ResourceType;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

test('resource_type_audit_trail_uses_user_guard', function () {
    // Arrange - create role and two users
    $role = Role::first() ?? Role::create([
        'name'            => 'Admin',
        'description'     => 'Administrator role',
        'permission_type' => 'all',
        'permissions'     => [],
    ]);

    $user1 = User::create([
        'name'     => 'Audit User 1',
        'email'    => 'audit.user1@example.com',
        'password' => bcrypt('password'),
        'status'   => 1,
        'role_id'  => $role->id,
    ]);

    $user2 = User::create([
        'name'     => 'Audit User 2',
        'email'    => 'audit.user2@example.com',
        'password' => bcrypt('password'),
        'status'   => 1,
        'role_id'  => $role->id,
    ]);

    // Act - authenticate via the "user" guard and create a resource type
    $this->actingAs($user1, 'user');

    $type = ResourceType::create([
        'name'        => 'X-Ray Machine',
        'description' => 'Radiology equipment',
    ]);

    // Assert - creation audit fields are set from user guard
    expect($type->created_by)->toBe($user1->id)
        ->and($type->updated_by)->toBe($user1->id)
        ->and($type->created_at)->not->toBeNull()
        ->and($type->updated_at)->not->toBeNull();

    // Act - switch user (user guard) and update
    $this->actingAs($user2, 'user');
    $type->update(['description' => 'Updated description']);

    // Assert - updater changed to second user
    expect($type->created_by)->toBe($user1->id)
        ->and($type->updated_by)->toBe($user2->id);
});
