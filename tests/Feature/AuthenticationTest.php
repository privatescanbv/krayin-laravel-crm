<?php

use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {

    $this->adminUserEmail = 'admin_authtest@example.com';
    if (! User::query()->where('email', $this->adminUserEmail)->exists()) {

        $role = Role::factory()->create([
            'name'            => 'Admin',
            'description'     => 'Administrator role',
            'permission_type' => 'all',
            'permissions'     => null,
        ]);

        // Zorg dat er een default admin user bestaat voor elke test
        User::factory()->create([
            'email'           => $this->adminUserEmail,
            'first_name'      => 'Admin',
            'last_name'       => 'User',
            'status'          => 1,
            'role_id'         => $role->id,
            'view_permission' => 'global',
        ]);
    }
});

it('can see the admin login page', function () {
    test()->get(route('admin.session.create'))
        ->assertOK();
});

it('can see the dashboard page after login', function () {
    $admin = User::where('email', $this->adminUserEmail)->first();

    test()->actingAs($admin)
        ->get(route('admin.dashboard.index'))
        ->assertOK();

    expect(auth()->guard('user')->user()->name)->toBe($admin->name);
});

it('can logout from the admin panel', function () {
    $admin = User::where('email', $this->adminUserEmail)->first();

    test()->actingAs($admin)
        ->delete(route('admin.session.destroy'), [
            '_token' => csrf_token(),
        ])
        ->assertStatus(302);

    expect(auth()->guard('user')->user())->toBeNull();
});
