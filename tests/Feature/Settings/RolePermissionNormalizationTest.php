<?php

use Database\Seeders\TestSeeder;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;
use Webkul\User\Services\RolePermissionNormalizer;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\put;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('role permission normalizer adds known parent acl keys', function () {
    $normalizer = new RolePermissionNormalizer;

    $normalized = $normalizer->normalize([
        'settings.clinics.view',
        'leads.view',
    ]);

    expect($normalized)
        ->toContain('settings', 'settings.clinics', 'settings.clinics.view', 'leads', 'leads.view')
        ->toHaveCount(5);
});

test('role update automatically includes parent permissions on save', function () {
    $adminRole = Role::factory()->create([
        'permission_type' => 'all',
        'permissions'     => null,
    ]);

    $admin = User::factory()->create([
        'role_id' => $adminRole->id,
        'status'  => 1,
    ]);

    $role = Role::factory()->create([
        'name'            => 'Test rol',
        'description'     => 'Test beschrijving',
        'permission_type' => 'custom',
        'permissions'     => ['leads.view'],
    ]);

    actingAs($admin, 'user');

    put(route('admin.settings.roles.update', $role->id), [
        'name'            => $role->name,
        'description'     => $role->description,
        'permission_type' => 'custom',
        'permissions'     => ['settings.clinics.view'],
    ])->assertRedirect();

    $role->refresh();

    expect($role->permissions)
        ->toContain('settings', 'settings.clinics', 'settings.clinics.view');
});
