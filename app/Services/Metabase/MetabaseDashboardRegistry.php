<?php

namespace App\Services\Metabase;

/**
 * Reads config/metabase_dashboards.php and exposes pages, ACL and menu items.
 */
class MetabaseDashboardRegistry
{
    public const PARENT_KEY = 'metabase';

    public const PARENT_NAME = 'Rapportages';

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     route: string,
     *     path: string,
     *     dashboard_id: int,
     *     params: array<string, mixed>,
     *     sort: int,
     *     icon-class: string
     * }>
     */
    public function pages(): array
    {
        $pages = [];

        foreach (config('metabase_dashboards', []) as $page) {
            if (! is_array($page)) {
                continue;
            }

            $normalized = $this->normalize($page);

            if ($normalized !== null) {
                $pages[] = $normalized;
            }
        }

        return $pages;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByKey(string $key): ?array
    {
        foreach ($this->pages() as $page) {
            if ($page['key'] === $key) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPath(string $path): ?array
    {
        $path = trim($path, '/');

        foreach ($this->pages() as $page) {
            if ($page['path'] === $path) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->findByPath('dashboards/'.$slug);
    }

    /**
     * ACL entries for pages that are not already defined in acl.php.
     *
     * @return list<array{key: string, name: string, route: string|array<int, string>, sort: int}>
     */
    public function aclItems(): array
    {
        return $this->itemsFor('acl');
    }

    /**
     * Menu entries for pages that are not already defined in menu.php.
     *
     * @return list<array{key: string, name: string, route: string, sort: int, icon-class: string}>
     */
    public function menuItems(): array
    {
        return $this->itemsFor('menu');
    }

    /**
     * Append missing ACL/menu items from the registry onto the live config.
     */
    public function mergeAclAndMenu(): void
    {
        $existingAclKeys = collect(config('acl', []))->pluck('key')->all();
        $aclToAdd = array_values(array_filter(
            $this->aclItems(),
            fn (array $item): bool => ! in_array($item['key'], $existingAclKeys, true)
        ));

        if ($aclToAdd !== []) {
            config(['acl' => array_merge(config('acl', []), $aclToAdd)]);
        }

        $existingMenuKeys = collect(config('menu.admin', []))->pluck('key')->all();
        $menuToAdd = array_values(array_filter(
            $this->menuItems(),
            fn (array $item): bool => ! in_array($item['key'], $existingMenuKeys, true)
        ));

        if ($menuToAdd !== []) {
            config(['menu.admin' => array_merge(config('menu.admin', []), $menuToAdd)]);
        }
    }

    /**
     * Extra dashboards (everything except the built-in /admin/dashboard page).
     *
     * @return list<array<string, mixed>>
     */
    public function extraPages(): array
    {
        return array_values(array_filter(
            $this->pages(),
            fn (array $page): bool => $page['key'] !== 'dashboard'
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemsFor(string $kind): array
    {
        $extras = $this->extraPages();

        if ($extras === []) {
            return [];
        }

        $items = [];

        if ($this->needsParent($extras)) {
            $first = $extras[0];
            $parent = [
                'key'   => self::PARENT_KEY,
                'name'  => self::PARENT_NAME,
                'route' => $first['route'],
                'sort'  => (int) min(array_column($extras, 'sort')),
            ];

            if ($kind === 'menu') {
                $parent['icon-class'] = $first['icon-class'] ?: 'icon-dashboard';
            }

            $items[] = $parent;
        }

        foreach ($extras as $page) {
            $item = [
                'key'   => $page['key'],
                'name'  => $page['name'],
                'route' => $page['route'],
                'sort'  => $page['sort'],
            ];

            if ($kind === 'menu') {
                $item['icon-class'] = $page['icon-class'];
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $extras
     */
    private function needsParent(array $extras): bool
    {
        foreach ($extras as $page) {
            if (str_starts_with($page['key'], self::PARENT_KEY.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>|null
     */
    private function normalize(array $page): ?array
    {
        $key = isset($page['key']) ? trim((string) $page['key']) : '';
        $dashboardId = isset($page['dashboard_id']) ? (int) $page['dashboard_id'] : 0;
        $path = isset($page['path']) ? trim((string) $page['path'], '/') : '';

        if ($key === '' || $dashboardId < 1 || $path === '') {
            return null;
        }

        $params = $page['params'] ?? [];

        if (! is_array($params)) {
            $params = [];
        }

        return [
            'key'          => $key,
            'name'         => (string) ($page['name'] ?? $key),
            'route'        => (string) ($page['route'] ?? $this->defaultRouteName($key, $path)),
            'path'         => $path,
            'dashboard_id' => $dashboardId,
            'params'       => $params,
            'sort'         => (int) ($page['sort'] ?? 50),
            'icon-class'   => (string) ($page['icon-class'] ?? 'icon-dashboard'),
        ];
    }

    private function defaultRouteName(string $key, string $path): string
    {
        if ($key === 'dashboard') {
            return 'admin.dashboard.index';
        }

        return 'admin.'.str_replace('/', '.', $path);
    }
}
