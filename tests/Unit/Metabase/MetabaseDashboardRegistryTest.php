<?php

use App\Services\Metabase\MetabaseDashboardRegistry;

it('normalizes the built-in dashboard page from config', function () {
    $page = (new MetabaseDashboardRegistry)->findByKey('dashboard');

    expect($page)->not->toBeNull()
        ->and($page['dashboard_id'])->toBe(3)
        ->and($page['route'])->toBe('admin.dashboard.index')
        ->and($page['path'])->toBe('dashboard')
        ->and($page['params'])->toBe(['periode' => 'past6months']);
});

it('does not emit acl or menu items for the existing dashboard key', function () {
    $registry = new MetabaseDashboardRegistry;

    expect($registry->aclItems())->toBe([])
        ->and($registry->menuItems())->toBe([]);
});

it('builds nested acl and menu items for extra dashboards', function () {
    config(['metabase_dashboards' => [
        [
            'key'          => 'dashboard',
            'name'         => 'Dashboard',
            'path'         => 'dashboard',
            'dashboard_id' => 3,
        ],
        [
            'key'          => 'metabase.verloren-leads',
            'name'         => 'Verloren leads',
            'path'         => 'dashboards/verloren-leads',
            'dashboard_id' => 4,
            'sort'         => 2,
            'icon-class'   => 'icon-dashboard',
        ],
    ]]);

    $registry = new MetabaseDashboardRegistry;

    expect($registry->aclItems())->toBe([
        [
            'key'   => 'metabase',
            'name'  => 'Rapportages',
            'route' => 'admin.dashboards.verloren-leads',
            'sort'  => 2,
        ],
        [
            'key'   => 'metabase.verloren-leads',
            'name'  => 'Verloren leads',
            'route' => 'admin.dashboards.verloren-leads',
            'sort'  => 2,
        ],
    ]);

    expect($registry->menuItems())->toEqualCanonicalizing([
        [
            'key'        => 'metabase',
            'name'       => 'Rapportages',
            'route'      => 'admin.dashboards.verloren-leads',
            'sort'       => 2,
            'icon-class' => 'icon-dashboard',
        ],
        [
            'key'        => 'metabase.verloren-leads',
            'name'       => 'Verloren leads',
            'route'      => 'admin.dashboards.verloren-leads',
            'sort'       => 2,
            'icon-class' => 'icon-dashboard',
        ],
    ]);
});

it('finds an extra page by slug', function () {
    config(['metabase_dashboards' => [
        [
            'key'          => 'metabase.secret',
            'name'         => 'Secret',
            'path'         => 'dashboards/secret',
            'dashboard_id' => 99,
        ],
    ]]);

    expect((new MetabaseDashboardRegistry)->findBySlug('secret')['key'])->toBe('metabase.secret');
});

it('merges extra acl keys without duplicating dashboard', function () {
    config(['metabase_dashboards' => [
        [
            'key'          => 'dashboard',
            'path'         => 'dashboard',
            'dashboard_id' => 3,
        ],
        [
            'key'          => 'metabase.omzet',
            'name'         => 'Omzet',
            'path'         => 'dashboards/omzet',
            'dashboard_id' => 8,
            'sort'         => 3,
        ],
    ]]);

    (new MetabaseDashboardRegistry)->mergeAclAndMenu();

    $aclKeys = collect(config('acl'))->pluck('key');
    $menuKeys = collect(config('menu.admin'))->pluck('key');

    expect($aclKeys->filter(fn ($key) => $key === 'dashboard')->count())->toBe(1)
        ->and($aclKeys)->toContain('metabase', 'metabase.omzet')
        ->and($menuKeys)->toContain('metabase', 'metabase.omzet');
});
