<?php

use App\Services\Metabase\MetabaseDashboardRegistry;

it('normalizes the leads-per-maand embed page from config', function () {
    $page = (new MetabaseDashboardRegistry)->findByKey('metabase.leads-per-maand');

    expect($page)->not->toBeNull()
        ->and($page['dashboard_id'])->toBe(3)
        ->and($page['route'])->toBe('admin.dashboards.leads-per-maand')
        ->and($page['path'])->toBe('dashboards/leads-per-maand')
        // No locked params: a param in the embed token hides the filter, and the
        // dashboard's own Periode default (this year) must stay editable.
        ->and($page['params'])->toBe([]);
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

it('groups report links into dashboard tiles in GROUPS order and drops empty groups', function () {
    $groups = (new MetabaseDashboardRegistry)->groupLinks([
        ['group' => 'Omzet', 'name' => 'Omzet per maand', 'url' => '/omzet'],
        ['group' => 'Onbekend', 'name' => 'Los rapport', 'url' => '/los'],
        ['group' => 'Leads', 'name' => 'Leads per maand', 'url' => '/leads'],
        ['group' => 'Omzet', 'name' => 'Omzet per medewerker', 'url' => '/medewerker'],
    ]);

    expect(array_column($groups, 'name'))->toBe(['Leads', 'Omzet', 'Overig'])
        ->and($groups[0]['icon'])->toBe('icon-leads')
        ->and(array_column($groups[1]['reports'], 'name'))->toBe(['Omzet per maand', 'Omzet per medewerker'])
        ->and($groups[2]['reports'])->toBe([['name' => 'Los rapport', 'url' => '/los']]);
});

it('puts every configured dashboard in a known group', function () {
    $groups = array_column((new MetabaseDashboardRegistry)->pages(), 'group');

    expect(array_diff($groups, array_keys(MetabaseDashboardRegistry::GROUPS)))->toBe([]);
});

it('lists the doorlooptijden dashboard under its own Operationeel tile', function () {
    $registry = new MetabaseDashboardRegistry;
    $page = $registry->findByKey('metabase.doorlooptijden');

    expect($page)->not->toBeNull()
        ->and($page['dashboard_id'])->toBe(11)
        ->and($page['route'])->toBe('admin.dashboards.doorlooptijden')
        ->and($page['params'])->toBe([]);

    $groups = $registry->groupLinks([['group' => $page['group'], 'name' => $page['name'], 'url' => '/x']]);

    expect($groups[0]['name'])->toBe('Operationeel')
        ->and($groups[0]['icon'])->toBe('icon-activity');
});
