<?php

/**
 * Metabase dashboards shown as CRM admin pages.
 *
 * Add a page: copy an extra entry, set key / path / dashboard_id, then:
 *  1. php artisan metabase:enable-embeds  (once per environment, if embedding is off)
 *  2. Instellingen → Rollen: tick the new permission
 *
 * `key` is the ACL + menu permission. `dashboard` reuses the existing Dashboard
 * permission and /admin/dashboard URL. Extra keys (e.g. metabase.verloren-leads)
 * appear automatically as nested items under Rapportages.
 */
return [
    [
        'key'          => 'dashboard',
        'name'         => 'admin::app.layouts.dashboard',
        'route'        => 'admin.dashboard.index',
        'path'         => 'dashboard',
        'dashboard_id' => 3,
        'params'       => [
            'periode' => 'past6months',
        ],
        'sort'         => 1,
        'icon-class'   => 'icon-dashboard',
    ],

    // Example extra page (uncomment and adjust dashboard_id):
    // [
    //     'key'          => 'metabase.verloren-leads',
    //     'name'         => 'Verloren leads',
    //     'path'         => 'dashboards/verloren-leads',
    //     'dashboard_id' => 4,
    //     'params'       => [],
    //     'sort'         => 2,
    //     'icon-class'   => 'icon-dashboard',
    // ],
];
