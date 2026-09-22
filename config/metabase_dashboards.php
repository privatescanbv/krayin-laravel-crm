<?php

/**
 * Metabase dashboards shown as CRM admin pages (guest embed via embed.js).
 *
 * You do NOT paste Metabase's HTML snippet per dashboard. One signing secret
 * (the hex METABASE_SECRET_KEY from Metabase's "Server code" — not an API key
 * mb_...) signs JWTs for every page. Per dashboard you only add dashboard_id.
 *
 * Add a page: copy an extra entry, set key / path / dashboard_id, then tick
 * the new permission in Instellingen → Rollen.
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
