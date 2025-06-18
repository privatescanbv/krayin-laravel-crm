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

    'base_url' => env('WEBHOOK_BASE_URL', 'http://host.docker.internal:5678'),

    'endpoints' => [
        'lead_pipeline_change'  => env('WEBHOOK_LEAD_PIPELINE_ENDPOINT', '/webhook-test/0de7745d-64c8-410b-9d23-f98f4b9c3787'),
        'lead_activity_is_done' => env('WEBHOOK_LEAD_ACTIVITY_ENDPOINT', ''),
    ],
];
