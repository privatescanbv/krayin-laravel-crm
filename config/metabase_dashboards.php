<?php

/**
 * Metabase dashboards shown as CRM admin pages (guest embed via embed.js).
 *
 * You do NOT paste Metabase's HTML snippet (embed.js / JWT) per dashboard.
 * One hex signing secret signs JWTs for every page.
 *
 * You DO have to publish each dashboard as a guest embed in Metabase
 * (Share → Embed → Guest → Publish). `php artisan metabase:enable-embeds`
 * does that via the API for every entry below.
 *
 * Add a page: copy an extra entry, set key / path / dashboard_id, run
 * `php artisan metabase:enable-embeds`, then tick the new permission in
 * Instellingen → Rollen.
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
        'embedding_params' => [
            'periode'  => 'enabled',
            'afdeling' => 'enabled',
            'campagne' => 'enabled',
            'leadbron' => 'enabled',
            'maand'    => 'enabled',
        ],
        'sort'         => 1,
        'icon-class'   => 'icon-dashboard',
    ],

    // Example extra page (uncomment and adjust dashboard_id):
    // [
    //     'key'          => 'metabase.verloren-leads',
    //     'name'         => 'Verloren leads',
    //     'path'         => 'dashboards/verloren-leads',
    //     'dashboard_id' => 5,
    //     'params'       => [],
    //     'embedding_params' => [
    //         'periode'  => 'enabled',
    //         'afdeling' => 'enabled',
    //         'campagne' => 'enabled',
    //         'bron'     => 'enabled',
    //         'maand'    => 'enabled',
    //     ],
    //     'sort'         => 2,
    //     'icon-class'   => 'icon-dashboard',
    // ],
];
