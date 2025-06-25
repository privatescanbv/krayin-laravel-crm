<?php

return [

    'dsn' => env('SENTRY_LARAVEL_DSN', 'https://9b157c9c4b52f450be722d155125453f@o481920.ingest.us.sentry.io/4509547637833728'),

    // Laravel log channels to capture (optional)
    'breadcrumbs' => [
        'logs'         => true,
        'sql_queries'  => true,
        'sql_bindings' => true,
        'queue_info'   => true,
    ],

    // Set this to false if you don't want to report Laravel logs as Sentry events
    'send_default_pii' => env('SENTRY_SEND_PII', false),

    // Performance tracing (for transactions, slow queries, etc.)
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),

    // Environment (optional)
    'environment' => env('APP_ENV', 'production'),

    // Release version (optional, useful for deployments)
    'release' => env('SENTRY_RELEASE'),

    // Capture Laravel events
    'breadcrumbs.sql_bindings' => true,

];
