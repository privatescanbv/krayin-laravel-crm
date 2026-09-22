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
        'sort'         => 1,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.verloren-leads',
        'name'         => 'Verloren leads',
        'path'         => 'dashboards/verloren-leads',
        'dashboard_id' => 4,
        'params'       => [],
        'sort'         => 2,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.omzet-per-medewerker',
        'name'         => 'Omzet per medewerker',
        'path'         => 'dashboards/omzet-per-medewerker',
        'dashboard_id' => 6,
        'params'       => [],
        'sort'         => 3,
        'icon-class'   => 'icon-dashboard',
    ],
];
