<?php

use App\Services\Metabase\MetabaseDashboardRegistry;

it('normalizes the leads-per-maand embed page from config', function () {
    $page = (new MetabaseDashboardRegistry)->findByKey('metabase.leads-per-maand');

    expect($page)->not->toBeNull()
        ->and($page['dashboard_id'])->toBe(3)
        ->and($page['route'])->toBe('admin.dashboards.leads-per-maand')
        ->and($page['path'])->toBe('dashboards/leads-per-maand')
        ->and($page['params'])->toBe(['periode' => 'past6months']);
});

it('ignores reserved dashboard key and path used by the native CRM dashboard', function () {
    config(['metabase_dashboards' => [
        [
            'key'          => 'dashboard',
            'name'         => 'Dashboard',
            'path'         => 'dashboard',
            'dashboard_id' => 3,
        ],
    ]]);

    expect((new MetabaseDashboardRegistry)->pages())->toBe([]);
});

it('does not emit menu items for metabase pages', function () {
    config(['metabase_dashboards' => [
        [
            'key'          => 'metabase.verloren-leads',
            'name'         => 'Verloren leads',
            'path'         => 'dashboards/verloren-leads',
            'dashboard_id' => 4,
            'sort'         => 2,
            'icon-class'   => 'icon-dashboard',
        ],
    ]]);

    expect((new MetabaseDashboardRegistry)->menuItems())->toBe([]);
});

it('builds nested acl items for extra dashboards', function () {
    config(['metabase_dashboards' => [
        [
            'key'          => 'metabase.leads-per-maand',
            'name'         => 'Leads per maand',
            'path'         => 'dashboards/leads-per-maand',
            'dashboard_id' => 3,
            'sort'         => 1,
            'icon-class'   => 'icon-dashboard',
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
            'route' => 'admin.dashboards.leads-per-maand',
            'sort'  => 1,
        ],
        [
            'key'   => 'metabase.leads-per-maand',
            'name'  => 'Leads per maand',
            'route' => 'admin.dashboards.leads-per-maand',
            'sort'  => 1,
        ],
        [
            'key'   => 'metabase.verloren-leads',
            'name'  => 'Verloren leads',
            'route' => 'admin.dashboards.verloren-leads',
            'sort'  => 2,
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

it('merges extra acl keys without adding menu items', function () {
    config(['metabase_dashboards' => [
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
        ->and($menuKeys)->not->toContain('metabase')
        ->and($menuKeys)->not->toContain('metabase.omzet');
});
