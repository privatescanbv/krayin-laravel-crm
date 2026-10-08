<?php

/**
 * Metabase dashboards shown as CRM admin pages.
 *
 * The native CRM dashboard stays at /admin/dashboard. Each entry below becomes
 * its own page plus a click-through link on that dashboard (Rapportages widget).
 * They are not added to the main menu.
 *
 * Add a page: copy an extra entry, set key / group / path / dashboard_id, then:
 *  1. php artisan metabase:enable-embeds  (once per environment, if embedding is off)
 *  2. Instellingen → Rollen: tick the new permission
 *
 * `group` is the tile the report is listed under on the dashboard; one of
 * MetabaseDashboardRegistry::GROUPS (anything else lands under "Overig").
 *
 * Do not use key/path `dashboard` — that URL is reserved for the native CRM dashboard.
 */
return [
    [
        'key'          => 'metabase.productverkopen',
        'group'        => 'Orders',
        'name'         => 'Productverkopen',
        'path'         => 'dashboards/productverkopen',
        'dashboard_id' => (int) env('METABASE_DASHBOARD_PRODUCTVERKOPEN', 2),
        'params'       => [],
        'sort'         => 2,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.leads-per-maand',
        'group'        => 'Leads',
        'name'         => 'Leads per maand',
        'path'         => 'dashboards/leads-per-maand',
        'dashboard_id' => (int) env('METABASE_DASHBOARD_LEADS_PER_MAAND', 3),
        'params'       => [],
        'sort'         => 3,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.verloren-leads',
        'group'        => 'Leads',
        'name'         => 'Verloren leads',
        'path'         => 'dashboards/verloren-leads',
        'dashboard_id' => (int) env('METABASE_DASHBOARD_VERLOREN_LEADS', 4),
        'params'       => [],
        'sort'         => 4,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.salesinspanning-conversie',
        'group'        => 'Leads',
        'name'         => 'Salesinspanning & conversie',
        'path'         => 'dashboards/salesinspanning-conversie',
        'dashboard_id' => (int) env('METABASE_DASHBOARD_SALESINSPANNING_CONVERSIE', 12),
        'params'       => [],
        'sort'         => 4,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.orders-per-maand',
        'group'        => 'Orders',
        'name'         => 'Orders per maand',
        'path'         => 'dashboards/orders-per-maand',
        'dashboard_id' => (int) env('METABASE_DASHBOARD_ORDERS_PER_MAAND', 5),
        'params'       => [],
        'sort'         => 5,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.omzet-per-medewerker',
        'group'        => 'Omzet',
        'name'         => 'Omzet per medewerker',
        'path'         => 'dashboards/omzet-per-medewerker',
        'dashboard_id' => (int) env('METABASE_DASHBOARD_OMZET_PER_MEDEWERKER', 6),
        'params'       => [],
        'sort'         => 6,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.omzet-per-maand',
        'group'        => 'Omzet',
        'name'         => 'Omzet per maand',
        'path'         => 'dashboards/omzet-per-maand',
        'dashboard_id' => (int) env('METABASE_DASHBOARD_OMZET_PER_MAAND', 7),
        'params'       => [],
        'sort'         => 7,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.verkooporder-op-onderzoeksdatum',
        'group'        => 'Orders',
        'name'         => 'Verkooporder op onderzoeksdatum',
        'path'         => 'dashboards/verkooporder-op-onderzoeksdatum',
        'dashboard_id' => (int) env('METABASE_DASHBOARD_VERKOOPORDER_OP_ONDERZOEKSDATUM', 8),
        //        'params'       => [
        //            'onderzoeksdatum' => 'thisweek',
        //        ],
        'sort'         => 8,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.hernia-beoordeling-operatie',
        'group'        => 'Herniapoli',
        'name'         => 'Herniapoli: beoordeling → operatie',
        'path'         => 'dashboards/hernia-beoordeling-operatie',
        'dashboard_id' => (int) env('METABASE_DASHBOARD_HERNIA_BEOORDELING_OPERATIE', 9),
        'params'       => [],
        'sort'         => 9,
        'icon-class'   => 'icon-dashboard',
    ],
    [
        'key'          => 'metabase.doorlooptijden',
        'group'        => 'Operationeel',
        'name'         => 'Doorlooptijden',
        'path'         => 'dashboards/doorlooptijden',
        'dashboard_id' => (int) env('METABASE_DASHBOARD_DOORLOOPTIJDEN', 11),
        'params'       => [],
        'sort'         => 10,
        'icon-class'   => 'icon-dashboard',
    ],
];
