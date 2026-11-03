<?php

use App\Enums\KeyCloakClient;
use App\Services\Keycloak\KeycloakRealmClientSeeder;

it('adds the :444 callback only for portal urls without a port', function (string $url, array $expected) {
    $config = KeycloakRealmClientSeeder::createKeycloakClientConfig('clinic-app', $url, KeyCloakClient::CLINIC);

    expect($config['clinic-app']['redirect_uris'])->toBe($expected);
})->with([
    'port-less url' => ['https://patientdev.local.privatescan.nl', [
        'https://patientdev.local.privatescan.nl/*',
        'https://patientdev.local.privatescan.nl/auth/callback',
        'https://patientdev.local.privatescan.nl:444/auth/callback',
    ]],
    'url with port' => ['https://clinic.local.privatescan.nl:445', [
        'https://clinic.local.privatescan.nl:445/*',
        'https://clinic.local.privatescan.nl:445/auth/callback',
    ]],
]);
