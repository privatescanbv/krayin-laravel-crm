<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Laravel Debugbar custom config
    |--------------------------------------------------------------------------
    |
    | We beperken de Debugbar tot echte ontwikkelomgevingen om 502‑errors
    | op `/_debugbar/assets/...` te vermijden wanneer de reverse proxy of
    | PHP‑FPM die route niet goed afhandelt (bijv. in Docker / HTTPS setup).
    |
    | - Standaard: alleen aan in `local` of `dev`
    | - Overschrijfbaar via DEBUGBAR_ENABLED in .env
    |
    */

    'enabled' => env('DEBUGBAR_ENABLED') !== null
        ? filter_var(env('DEBUGBAR_ENABLED'), FILTER_VALIDATE_BOOLEAN)
        : in_array(env('APP_ENV', 'production'), ['local', 'dev'], true),

];
