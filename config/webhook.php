<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the webhook settings for your application.
    |
    */

    'base_url'    => env('WEBHOOK_BASE_URL', 'http://n8n:5678'),
    'n8n_api_key' => env('N8N_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook Enable/Disable
    |--------------------------------------------------------------------------
    |
    | This setting allows you to globally disable all webhooks.
    | Useful during import operations or maintenance.
    |
    */
    'enabled' => env('WEBHOOKS_ENABLED', true),

    'endpoints' => [
        'lead_pipeline_change'  => env('WEBHOOK_LEAD_PIPELINE_ENDPOINT', '/webhook-test/0de7745d-64c8-410b-9d23-f98f4b9c3787'),
        'lead_activity_is_done' => env('WEBHOOK_LEAD_ACTIVITY_ENDPOINT', ''),
        'sales_lead_pipeline_change'  => env('WEBHOOK_SALES_LEAD_PIPELINE_ENDPOINT', '/webhook-test/0de7745d-64c8-410b-9d23-f98f4b9c3787'),
    ],
];
