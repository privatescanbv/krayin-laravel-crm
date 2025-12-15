<?php

use App\Enums\KeyCloakClient;

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

    'keycloak' => [
        'client_id'                   => KeyCloakClient::CRM->clientId(),
        'client_secret'               => env(KeyCloakClient::CRM->envKeySecret()),
        'redirect'                    => env('KEYCLOAK_REDIRECT_URI', '/admin/auth/keycloak/callback'),
        'base_url_external'           => env('KEYCLOAK_EXTERNAL_BASE_URL', 'http://keycloak.local'),
        'base_url_internal'           => env('KEYCLOAK_INTERNAL_BASE_URL', 'http://keycloak.local:8080'),
        'realm'                       => env('KEYCLOAK_REALM', 'master'),
        'default_role_id'             => env('KEYCLOAK_DEFAULT_ROLE_ID', 1),
        'admin_username'              => env('KEYCLOAK_ADMIN', 'admin'),
        'admin_password'              => env('KEYCLOAK_ADMIN_PASSWORD', 'changeme'),
        'themes'                      => [
            'login' => 'privatescan',
        ],
    ],

    'portal' => [
        'patient' => [
            'web_url'   => env('PATIENT_PORTAL_URL', 'https://patientdev.local.privatescan.nl'),
            'api_url'   => env('PATIENT_PORTAL_API_URL', env('PATIENT_PORTAL_URL')),
            'api_token' => env('FORMS_API_KEY'),
            'secret'    => env(KeyCloakClient::PATIENT->envKeySecret()),
        ],
        'clinic' => [
            'web_url' => env('CLINIC_PORTAL_URL', 'https://clinic.local.privatescan.nl'),
            'secret'  => env(KeyCloakClient::CLINIC->envKeySecret()),
        ],
        'employee' => [
            'web_url' => env('EMPLOYEE_PORTAL_URL', 'https://employee.local.privatescan.nl'),
            'secret'  => env(KeyCloakClient::EMPLOYEE->envKeySecret()),
        ],
    ],

];
