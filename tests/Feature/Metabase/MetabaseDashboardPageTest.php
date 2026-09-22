<?php

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
        ->assertSee('v-dashboard-over-all-stats', false)
        ->assertSee('Leads per maand', false)
        ->assertSee(route('admin.dashboards.leads-per-maand'), false)
        ->assertDontSee('<metabase-dashboard', false);
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
        ->assertSee('metabase-dashboard', false)
        ->assertSee('initial-parameters', false);
});

it('renders the leads-per-maand embed on its own page', function () {
    $user = makeCustomUser(['metabase.leads-per-maand']);

    $this->actingAs($user, 'user')
        ->get(route('admin.dashboards.leads-per-maand'))
        ->assertOk()
        ->assertSee('Leads per maand', false)
        ->assertSee('metabase-dashboard', false)
        ->assertSee('/app/embed.js', false)
        ->assertSee('isGuest', false);
});

it('returns 404 for an unknown extra dashboard slug', function () {
    $user = makeCustomUser(['dashboard']);

    $this->actingAs($user, 'user')
        ->get(route('admin.metabase-dashboards.show', ['slug' => 'does-not-exist']))
        ->assertNotFound();
});
