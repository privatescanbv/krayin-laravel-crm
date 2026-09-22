<?php

use Webkul\Core\Menu;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

function makeCustomUser(array $permissions): User
{
    $role = Role::factory()->create([
        'name'            => 'Custom '.uniqid(),
        'description'     => 'Custom role',
        'permission_type' => 'custom',
        'permissions'     => $permissions,
    ]);

    return User::factory()->create([
        'status'  => 1,
        'role_id' => $role->id,
    ]);
}

beforeEach(function () {
    $this->withoutVite();
});

it('renders the native crm dashboard with links to metabase pages the role may view', function () {
    $user = makeCustomUser(['dashboard', 'metabase.leads-per-maand']);

    $this->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertDontSee('v-dashboard-over-all-stats', false)
        ->assertSee('Leads per maand', false)
        ->assertSee(route('admin.dashboards.leads-per-maand'), false)
        ->assertDontSee('/embed/dashboard/', false);
});

it('hides metabase links the role may not view', function () {
    config(['metabase_dashboards' => array_merge(config('metabase_dashboards'), [[
        'key'          => 'metabase.secret',
        'name'         => 'Secret dashboard',
        'path'         => 'dashboards/secret',
        'dashboard_id' => 99,
        'params'       => [],
        'sort'         => 9,
        'icon-class'   => 'icon-dashboard',
    ]])]);

    $user = makeCustomUser(['dashboard', 'metabase.leads-per-maand']);

    $this->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->assertSee('Leads per maand', false)
        ->assertDontSee('Secret dashboard', false);
});

it('does not show rapportages in the admin menu', function () {
    $user = makeCustomUser(['dashboard', 'metabase', 'metabase.leads-per-maand']);

    $this->actingAs($user, 'user');

    $menuKeys = (new Menu)->getItems(Menu::ADMIN)->map(fn ($item) => $item->getKey())->all();

    expect($menuKeys)->not->toContain('metabase');
});

it('denies the dashboard without the dashboard permission', function () {
    $user = makeCustomUser(['leads']);

    $this->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'))
        ->assertUnauthorized();
});

it('denies an extra metabase page when the role lacks that permission', function () {
    config(['metabase_dashboards' => array_merge(config('metabase_dashboards'), [[
        'key'          => 'metabase.secret',
        'name'         => 'Secret dashboard',
        'path'         => 'dashboards/secret',
        'dashboard_id' => 99,
        'params'       => [],
        'sort'         => 9,
        'icon-class'   => 'icon-dashboard',
    ]])]);

    $user = makeCustomUser(['dashboard']);

    $this->actingAs($user, 'user')
        ->get(route('admin.metabase-dashboards.show', ['slug' => 'secret']))
        ->assertUnauthorized();
});

it('renders an extra metabase page for a role that may view it', function () {
    config(['metabase_dashboards' => array_merge(config('metabase_dashboards'), [[
        'key'          => 'metabase.secret',
        'name'         => 'Secret dashboard',
        'path'         => 'dashboards/secret',
        'dashboard_id' => 99,
        'params'       => ['periode' => 'past6months'],
        'sort'         => 9,
        'icon-class'   => 'icon-dashboard',
    ]])]);

    $user = makeCustomUser(['metabase.secret']);

    $this->actingAs($user, 'user')
        ->get(route('admin.metabase-dashboards.show', ['slug' => 'secret']))
        ->assertOk()
        ->assertSee('Secret dashboard', false)
        ->assertSee('/embed/dashboard/', false);
});

it('renders the leads-per-maand embed on its own page', function () {
    $user = makeCustomUser(['metabase.leads-per-maand']);

    $this->actingAs($user, 'user')
        ->get(route('admin.dashboards.leads-per-maand'))
        ->assertOk()
        ->assertSee('Leads per maand', false)
        ->assertSee('/embed/dashboard/', false);
});

it('returns 404 for an unknown extra dashboard slug', function () {
    $user = makeCustomUser(['dashboard']);

    $this->actingAs($user, 'user')
        ->get(route('admin.metabase-dashboards.show', ['slug' => 'does-not-exist']))
        ->assertNotFound();
});
