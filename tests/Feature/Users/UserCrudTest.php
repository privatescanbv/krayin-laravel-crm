<?php

use App\Models\Department;
use Database\Seeders\TestSeeder;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\put;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    // Ensure we have an authenticated user for the admin guard
    $this->user = User::factory()->create();
    // Ensure at least one role exists for payloads
    $this->role = Role::query()->first() ?? Role::factory()->create();
});

it('updates user with valid department default', function () {
    $user = $this->user;
    $role = $this->role;
    $herniaId = Department::findHerniaId();

    $payload = [
        'email'               => $user->email,
        'first_name'          => 'Admin',
        'password'            => '123',
        'confirm_password'    => '123',
        'last_name'           => 'Tester',
        'role_id'             => $role->id,
        'view_permission'     => 'global',
        'groups'              => [],
        'user_default_values' => [
            'lead.department_id' => (string) $herniaId,
        ],
    ];

    actingAs($user, 'user');
    $resp = put(route('admin.settings.users.update', ['id' => $user->id]), $payload);
    $resp->assertRedirect(route('admin.settings.users.index'));
});

it('rejects update when department default is invalid', function () {
    $user = $this->user;
    $role = $this->role;

    $payload = [
        'email'               => $user->email,
        'first_name'          => 'Admin',
        'last_name'           => 'Tester',
        'role_id'             => $role->id,
        'view_permission'     => 'global',
        'groups'              => [],
        'user_default_values' => [
            'lead.department_id' => '999999',
        ],
    ];

    actingAs($user, 'user');
    $resp = put(route('admin.settings.users.update', ['id' => $user->id]), $payload);
    $resp->assertStatus(302)
        ->assertSessionHasErrors(['user_default_values.lead.department_id']);
});

test('can add user', function () {
    $role = Role::query()->first() ?? Role::factory()->create();

    $payload = [
        'email'            => 'new.user@example.com',
        'first_name'       => 'New',
        'last_name'        => 'User',
        'role_id'          => $role->id,
        'view_permission'  => 'global',
        'groups'           => [],
        // password is optional in controller rules; include to avoid unique issues later
        'password'         => 'secret123',
        'confirm_password' => 'secret123',
    ];
    $user = $this->user;
    actingAs($user, 'user');
    $response = $this->postJson(route('admin.settings.users.store'), $payload);
    $response->assertOk()->assertJsonStructure(['data', 'message']);

    $this->assertDatabaseHas('users', [
        'email'      => 'new.user@example.com',
        'first_name' => 'New',
        'last_name'  => 'User',
    ]);
});
