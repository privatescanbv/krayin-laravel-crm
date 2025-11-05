<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'postcodeapi' => [
        'url'   => 'https://sandbox.postcodeapi.nu/v3/lookup/',
        'token' => env('POSTCODEAPI_TOKEN'),
    ],

    'sugarcrm' => [
        'base_url' => env('SUGARCRM_BASE_URL', 'http://localhost:81/'),
    ],

    'forms' => [
        'api_url'      => env('FORMS_API_URL', 'http://forms'),
        'frontend_url' => env('FORMS_FRONTEND_URL', 'http://localhost:8001'),
        'api_token'    => env('FORMS_API_KEY', null),
    ],

];
