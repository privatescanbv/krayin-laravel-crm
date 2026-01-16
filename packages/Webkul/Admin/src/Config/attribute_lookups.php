<?php

return [
    'leads' => [
        'name'         => 'Leads',
        'repository'   => 'Webkul\Lead\Repositories\LeadRepository',
        // Title column removed on leads; UI expects a name-like label.
        // We map label_column to a virtual accessor via LeadRepository select (CONCAT first + last name).
        'label_column' => 'name',
    ],

    'lead_sources' => [
        'name'         => 'Lead Sources',
        'repository'   => 'Webkul\Lead\Repositories\SourceRepository',
    ],

    'lead_types' => [
        'name'         => 'Lead Types',
        'repository'   => 'Webkul\Lead\Repositories\TypeRepository',
    ],

    'lead_pipelines' => [
        'name'         => 'Lead Pipelines',
        'repository'   => 'Webkul\Lead\Repositories\PipelineRepository',
    ],

    'lead_pipeline_stages' => [
        'name'         => 'Lead Pipeline Stages',
        'repository'   => 'Webkul\Lead\Repositories\StageRepository',
    ],

    'users' => [
        'name'         => 'Sales Owners',
        'repository'   => 'Webkul\User\Repositories\UserRepository',
    ],

    'organizations' => [
        'name'         => 'Organizations',
        'repository'   => 'Webkul\Contact\Repositories\OrganizationRepository',
    ],

    'persons' => [
        'name'         => 'Persons',
        'repository'   => 'Webkul\Contact\Repositories\PersonRepository',
    ],
    'partner_products' => [
        'name'         => 'Partner Products',
        'repository'   => 'App\Repositories\PartnerProductRepository',
        'label_column' => 'name',
    ],
    'product_groups' => [
        'name'         => 'Product Groups',
        'repository'   => 'Webkul\Product\Repositories\ProductGroupRepository',
        'label_column' => 'name',
    ],
];
