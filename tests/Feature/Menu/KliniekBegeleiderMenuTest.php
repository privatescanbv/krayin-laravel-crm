<?php

use Database\Seeders\TestSeeder;
use Webkul\Core\Menu;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

function kliniekBegeleiderPermissions(): array
{
    return [
        'operational-dashboard',
        'dashboard',
        'leads',
        'leads.view',
        'sales-leads',
        'sales-leads.view',
        'sales-leads.edit',
        'orders',
        'orders.view',
        'orders.edit',
        'products',
        'products.view',
        'productgroups',
        'productgroups.view',
        'mail',
        'mail.view',
        'partner_products',
        'partner_products.view',
        'settings.clinics',
        'settings.clinics.view',
        'settings.resources',
        'settings.resources.view',
        'clinic-guide',
        'contacts',
        'contacts.persons',
        'contacts.persons.view',
        'contacts.persons.impersonate',
        'contacts.organizations',
        'contacts.organizations.view',
        'activities',
        'activities.create',
        'activities.edit',
        'resource_planning',
        'documentation',
    ];
}

function createKliniekBegeleider(): User
{
    $role = Role::factory()->create([
        'name'            => 'Kliniek Begeleider',
        'permission_type' => 'custom',
        'permissions'     => kliniekBegeleiderPermissions(),
    ]);

    return User::factory()->create([
        'role_id'         => $role->id,
        'view_permission' => 'global',
        'status'          => 1,
    ]);
}

test('kliniek begeleider can load admin leads page without menu error', function () {
    $user = createKliniekBegeleider();

    actingAs($user, 'user')
        ->get(route('admin.leads.index'))
        ->assertOk();
});

test('kliniek begeleider menu excludes settings submenu without settings permission', function () {
    $user = createKliniekBegeleider();

    actingAs($user, 'user');

    $menuItems = (new Menu)->getItems(Menu::ADMIN);
    $menuKeys = $menuItems->map(fn ($item) => $item->getKey())->all();

    expect($menuKeys)
        ->toContain('clinic-guide', 'leads')
        ->not->toContain('settings');
});
