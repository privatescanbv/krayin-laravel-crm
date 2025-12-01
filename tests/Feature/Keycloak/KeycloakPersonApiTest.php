<?php

namespace Tests\Feature\Keycloak;

use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Config;
use Webkul\Contact\Models\Person;

beforeEach(function () {
    // Minimal seeding (personen worden in de test zelf aangemaakt)
    $this->seed(TestSeeder::class);

    // Zorg dat de API key‑middleware een geldige sleutel heeft tijdens deze tests
    Config::set('api.keys', ['valid-api-key-123']);
});

it('geeft person id terug voor een bestaand keycloak user id', function () {
    $keycloakUserId = '11111111-2222-3333-4444-555555555555';

    /** @var Person $person */
    $person = Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'is_active'        => true,
    ]);

    $response = $this
        ->withHeaders([
            'X-API-KEY' => 'valid-api-key-123',
            'Accept'    => 'application/json',
        ])
        ->getJson("/api/keycloak/persons/{$keycloakUserId}");

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data'    => [
                'person_id'        => $person->id,
                'user_id'          => $person->user_id,
                'keycloak_user_id' => $keycloakUserId,
                'is_active'        => true,
            ],
        ]);
});

it('geeft 404 terug als keycloak user id niet bestaat', function () {
    $nonExistentKeycloakUserId = '99999999-9999-9999-9999-999999999999';

    $response = $this
        ->withHeaders([
            'X-API-KEY' => 'valid-api-key-123',
            'Accept'    => 'application/json',
        ])
        ->getJson("/api/keycloak/persons/{$nonExistentKeycloakUserId}");

    $response
        ->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'Geen persoon gevonden voor opgegeven Keycloak user id.',
        ]);
});
