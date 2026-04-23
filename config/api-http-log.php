<?php

return [

    'enabled' => env('API_HTTP_LOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Log channel for api_http records
    |--------------------------------------------------------------------------
    |
    | Defaults to LOG_CHANNEL so traffic logs follow the same sinks as the rest
    | of the app (e.g. LOG_CHANNEL=stack → stderr JSON for Loki/Grafana).
    | Set API_HTTP_LOG_CHANNEL=api_http only if you want a separate daily file
    | at storage/logs/api-http.log.
    |
    */
    'channel' => filled(env('API_HTTP_LOG_CHANNEL'))
        ? env('API_HTTP_LOG_CHANNEL')
        : env('LOG_CHANNEL', 'stack'),

    'max_request_body_bytes' => (int) env('API_HTTP_LOG_MAX_REQUEST_BODY', 8192),

    'max_response_body_bytes' => (int) env('API_HTTP_LOG_MAX_RESPONSE_BODY', 8192),

    'log_response_headers' => env('API_HTTP_LOG_RESPONSE_HEADERS', true),

    /*
    |--------------------------------------------------------------------------
    | Header names (case-insensitive) replaced with [REDACTED] in logs.
    |--------------------------------------------------------------------------
    */
    'redact_headers' => [
        'authorization',
        'x-api-key',
        'cookie',
        'set-cookie',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request input / JSON body keys (case-insensitive, nested via dot flatten).
    |--------------------------------------------------------------------------
    */
    'redact_input_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'api_key',
        'x-api-key',
    ],
];
