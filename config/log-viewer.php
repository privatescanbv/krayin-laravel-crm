<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Log Viewer Inschakelen
    |--------------------------------------------------------------------------
    |
    | Zet deze waarde op true om toegang tot de log viewer toe te staan.
    | Bijv. in je .env: LOG_VIEWER_ENABLED=true
    |
    */

    'enabled' => env('LOG_VIEWER_ENABLED', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | URL Pad
    |--------------------------------------------------------------------------
    |
    | Het pad waarop de log viewer bereikbaar is (bijv. /log-viewer)
    |
    */

    'path' => 'log-viewer',

    /*
    |--------------------------------------------------------------------------
    | Toegestane IP-adressen (optioneel)
    |--------------------------------------------------------------------------
    |
    | Laat leeg om iedereen toegang te geven (op eigen risico!)
    | Vul bijvoorbeeld in: ['127.0.0.1', '::1', '192.168.1.5']
    |
    */

    'allowed_ips' => [],

    /*
    |--------------------------------------------------------------------------
    | Middelen
    |--------------------------------------------------------------------------
    |
    | Je kunt hier middleware toevoegen zoals 'auth' of 'admin'.
    |
    */

    'middleware' => ['web'],
];
