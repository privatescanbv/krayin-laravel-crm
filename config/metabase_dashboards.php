<?php

/**
 * Metabase dashboards shown as CRM admin pages.
 *
 * The native CRM dashboard stays at /admin/dashboard. Each entry below becomes
 * its own page plus a click-through link on that dashboard (Rapportages widget).
 * They are not added to the main menu.
 *
 * Add a page: copy an extra entry, set key / path / dashboard_id, then:
 *  1. php artisan metabase:enable-embeds  (once per environment, if embedding is off)
 *  2. Instellingen → Rollen: tick the new permission
 *
 * Do not use key/path `dashboard` — that URL is reserved for the native CRM dashboard.
 */
return [
    [
        'key'          => 'metabase.leads-per-maand',
        'name'         => 'Leads per maand',
        'path'         => 'dashboards/leads-per-maand',
        'dashboard_id' => 3,
        'params'       => [
            'periode' => 'past6months',
        ],
        'sort'         => 3,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.verloren-leads',
        'name'         => 'Verloren leads',
        'path'         => 'dashboards/verloren-leads',
        'dashboard_id' => 4,
        'params'       => [],
        'sort'         => 4,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.orders-per-maand',
        'name'         => 'Orders per maand',
        'path'         => 'dashboards/orders-per-maand',
        'dashboard_id' => 5,
        'params'       => [],
        'sort'         => 5,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.omzet-per-medewerker',
        'name'         => 'Omzet per medewerker',
        'path'         => 'dashboards/omzet-per-medewerker',
        'dashboard_id' => 6,
        'params'       => [],
        'sort'         => 6,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.omzet-per-maand',
        'name'         => 'Omzet per maand',
        'path'         => 'dashboards/omzet-per-maand',
        'dashboard_id' => 7,
        'params'       => [],
        'sort'         => 7,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.verkooporder-op-onderzoeksdatum',
        'name'         => 'Verkooporder op onderzoeksdatum',
        'path'         => 'dashboards/verkooporder-op-onderzoeksdatum',
        'dashboard_id' => 8,
        'params'       => [
            'onderzoeksdatum' => 'thisweek',
        ],
        'sort'         => 8,
        'icon-class'   => 'icon-dashboard',
    ],
];
