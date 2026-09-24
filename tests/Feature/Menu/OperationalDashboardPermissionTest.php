<?php

use Database\Seeders\TestSeeder;
use Webkul\Core\Menu;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

function userWithPermissions(array $permissions): User
{
    $role = Role::factory()->create([
        'permission_type' => 'custom',
        'permissions'     => $permissions,
    ]);

    return User::factory()->create(['role_id' => $role->id, 'view_permission' => 'global', 'status' => 1]);
}

test('werkbakken is a selectable permission in the role editor', function () {
    expect(collect(config('acl'))->pluck('key'))->toContain('operational-dashboard');
});

test('werkbakken menu item follows the operational-dashboard permission', function (array $permissions, bool $visible) {
    actingAs(userWithPermissions($permissions), 'user');

    $menuKeys = (new Menu)->getItems(Menu::ADMIN)->map(fn ($item) => $item->getKey())->all();

    expect(in_array('operational-dashboard', $menuKeys, true))->toBe($visible);
})->with([
    'with permission'    => [['operational-dashboard', 'dashboard'], true],
    'without permission' => [['dashboard'], false],
]);

test('migration grants werkbakken to medewerker and kliniek begeleider only', function () {
    $medewerker = Role::factory()->create(['name' => 'Medewerker Afdeling', 'permission_type' => 'custom', 'permissions' => ['dashboard']]);
    $kliniek = Role::factory()->create(['name' => 'Kliniek Begeleider', 'permission_type' => 'custom', 'permissions' => ['dashboard']]);
    $other = Role::factory()->create(['name' => 'Andere rol', 'permission_type' => 'custom', 'permissions' => ['dashboard']]);

    (require database_path('migrations/2026_09_24_100000_grant_operational_dashboard_to_employee_roles.php'))->up();

    expect($medewerker->fresh()->permissions)->toContain('operational-dashboard', 'dashboard')
        ->and($kliniek->fresh()->permissions)->toContain('operational-dashboard')
        ->and($other->fresh()->permissions)->toBe(['dashboard']);
});
