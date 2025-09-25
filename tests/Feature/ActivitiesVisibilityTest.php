<?php

namespace Tests\Feature;

use Webkul\Activity\Models\Activity;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\User\Models\Group;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    // Keep consistent with other feature tests
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);

    // Avoid redirects to installer during tests
    test()->withoutMiddleware(CanInstall::class);
});

function makeGroup(string $name): Group
{
    return Group::firstOrCreate(['name' => $name]);
}


function makeActivity(array $attrs = []): Activity
{
    // Ensure we have a default group_id if not provided
    if (! isset($attrs['group_id'])) {
        $defaultGroup = Group::first() ?? Group::create(['name' => 'Default Group']);
        $attrs['group_id'] = $defaultGroup->id;
    }

    return Activity::create(array_merge([
        'type'          => 'task',
        'title'         => 'Test Activity',
        'schedule_from' => now()->format('Y-m-d H:i:s'),
        'schedule_to'   => now()->addHour()->format('Y-m-d H:i:s'),
        'is_done'       => 0,
    ], $attrs));
}


// 1) Global admin in privatescan view: only activities from Privatescan

test('global admin sees only privatescan activities in privatescan view', function () {
    // Arrange
    $adminRole = Role::factory()->create(['permission_type' => 'all']);
    $admin = makeUser(['role_id' => $adminRole->id]);

    $privatescan = makeGroup('Privatescan');
    $hernia = makeGroup('Hernia');

    $inPs1 = makeActivity(['group_id' => $privatescan->id]);
    $inPs2 = makeActivity(['group_id' => $privatescan->id]);
    $inHe1 = makeActivity(['group_id' => $hernia->id]);

    // Act
    $this->actingAs($admin, 'user');
    $response = $this->getJson('/admin/activities/get?view=privatescan');

    // Assert
    $response->assertOk();
    $ids = getDatagridIds($response);
    expect($ids)->toContain($inPs1->id, $inPs2->id)
        ->and($ids)->not()->toContain($inHe1->id);
});

// 2) Custom role with takeover permission can see others' assigned activities (same group)

test('user with takeover permission sees activities assigned to others in same group', function () {
    // Arrange
    $roleWithTakeover = Role::factory()->create([
        'permission_type' => 'custom',
        'permissions'     => ['activities.takeover'],
    ]);
    $user = makeUser(['role_id' => $roleWithTakeover->id]);
    $otherUser = makeUser(['role_id' => $roleWithTakeover->id]);

    $privatescan = makeGroup('Privatescan');
    // Viewer is member of the group, so group-based visibility applies
    $user->groups()->attach($privatescan->id);

    $own = makeActivity(['group_id' => $privatescan->id, 'user_id' => $user->id]);
    $oth1 = makeActivity(['group_id' => $privatescan->id, 'user_id' => $otherUser->id]);

    // Act
    $this->actingAs($user, 'user');
    $response = $this->getJson('/admin/activities/get?view=privatescan');

    // Assert
    $response->assertOk();
    $ids = getDatagridIds($response);
    expect($ids)->toContain($own->id)
        ->and($ids)->toContain($oth1->id);
});

// 3) Non-admin without takeover should NOT see others' assigned activities (same group)

test('user without takeover does not see activities assigned to others', function () {
    // Arrange
    $roleNoTakeover = Role::factory()->create([
        'permission_type' => 'custom',
        'permissions'     => ['activities.view'],
    ]);
    $user = makeUser(['role_id' => $roleNoTakeover->id]);
    $otherUser = makeUser(['role_id' => $roleNoTakeover->id]);

    $privatescan = makeGroup('Privatescan');

    $own = makeActivity(['group_id' => $privatescan->id, 'user_id' => $user->id]);
    $oth1 = makeActivity(['group_id' => $privatescan->id, 'user_id' => $otherUser->id]);

    // Act
    $this->actingAs($user, 'user');
    $response = $this->getJson('/admin/activities/get?view=privatescan');

    // Assert
    $response->assertOk();
    $ids = getDatagridIds($response);
    expect($ids)->toContain($own->id)
        ->and($ids)->not()->toContain($oth1->id);
});
