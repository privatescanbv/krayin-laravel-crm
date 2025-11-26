<?php

namespace Tests\Feature\Keycloak;

use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // Voorkom ruis in de testoutput
    Config::set('breadcrumbs.files', []);

    // Zorg dat de API key‑middleware een geldige sleutel heeft tijdens deze tests
    Config::set('api.keys', ['valid-api-key-123', 'another-valid-key']);
});

it('accepteert een webhook met geldige API key wanneer er geen webhook secret is geconfigureerd', function () {
    $payload = [
        'type' => 'USER',
        'data' => ['foo' => 'bar'],
    ];

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson('/api/keycloak/webhooks', $payload);

    $response
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});
