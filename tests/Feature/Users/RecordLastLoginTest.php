<?php

use App\Actions\Users\RecordLastLogin;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

it('records last login timestamp without touching model observers', function () {
    $role = Role::factory()->create(['permission_type' => 'all']);
    $user = User::factory()->create([
        'role_id'         => $role->id,
        'status'          => 1,
        'view_permission' => 'global',
        'last_login_at'   => null,
    ]);

    app(RecordLastLogin::class)($user);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('records last login on successful admin password login', function () {
    $role = Role::factory()->create(['permission_type' => 'all']);
    $user = User::factory()->create([
        'email'           => 'login-audit@example.com',
        'password'        => 'password',
        'role_id'         => $role->id,
        'status'          => 1,
        'view_permission' => 'global',
        'last_login_at'   => null,
    ]);

    $this->post(route('admin.session.store'), [
        'email'    => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('does not record last login when credentials are invalid', function () {
    $role = Role::factory()->create(['permission_type' => 'all']);
    $user = User::factory()->create([
        'email'           => 'login-fail@example.com',
        'password'        => 'password',
        'role_id'         => $role->id,
        'status'          => 1,
        'view_permission' => 'global',
        'last_login_at'   => null,
    ]);

    $this->post(route('admin.session.store'), [
        'email'    => $user->email,
        'password' => 'wrong-password',
    ])->assertRedirect();

    expect($user->fresh()->last_login_at)->toBeNull();
});

it('does not record last login for inactive users', function () {
    $role = Role::factory()->create(['permission_type' => 'all']);
    $user = User::factory()->create([
        'email'           => 'inactive-login@example.com',
        'password'        => 'password',
        'role_id'         => $role->id,
        'status'          => 0,
        'view_permission' => 'global',
        'last_login_at'   => null,
    ]);

    $this->post(route('admin.session.store'), [
        'email'    => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    expect($user->fresh()->last_login_at)->toBeNull();
});

it('exposes last login in users datagrid', function () {
    $role = Role::factory()->create(['permission_type' => 'all']);
    $admin = User::factory()->create([
        'role_id'         => $role->id,
        'status'          => 1,
        'view_permission' => 'global',
    ]);
    $user = User::factory()->create([
        'role_id'       => $role->id,
        'status'        => 1,
        'last_login_at' => now()->subDay(),
    ]);

    $this->actingAs($admin, 'user');

    $response = $this->getJson(route('admin.settings.users.index'), [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk();

    $record = collect($response->json('records'))->firstWhere('id', $user->id);

    expect($record)->not->toBeNull()
        ->and($record)->toHaveKey('last_login_at');
});
