<?php

namespace Tests\Feature\Keycloak;

use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;
use Mockery;
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

it('accepts keycloak bearer token for own keycloak person lookup', function () {
    config(['api.keys' => []]);

    $keycloakUserId = '22222222-3333-4444-5555-666666666666';

    /** @var Person $person */
    $person = Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'is_active'        => true,
    ]);

    $socialiteUser = new User;
    $socialiteUser->setRaw(['sub' => $keycloakUserId]);
    $socialiteUser->map(['id' => $keycloakUserId]);

    $provider = Mockery::mock();
    $provider->shouldReceive('userFromToken')->once()->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('keycloak')->once()->andReturn($provider);

    $response = $this->getJson(
        "/api/keycloak/persons/{$keycloakUserId}",
        ['Authorization' => 'Bearer test-token']
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.person_id', $person->id);
});

it('forbids keycloak bearer token for another users keycloak person lookup', function () {
    config(['api.keys' => []]);

    $tokenSubject = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    $otherKeycloakUserId = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';

    Person::factory()->create([
        'keycloak_user_id' => $otherKeycloakUserId,
        'is_active'        => true,
    ]);

    $socialiteUser = new User;
    $socialiteUser->setRaw(['sub' => $tokenSubject]);
    $socialiteUser->map(['id' => $tokenSubject]);

    $provider = Mockery::mock();
    $provider->shouldReceive('userFromToken')->once()->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('keycloak')->once()->andReturn($provider);

    $response = $this->getJson(
        "/api/keycloak/persons/{$otherKeycloakUserId}",
        ['Authorization' => 'Bearer test-token']
    );

    $response->assertForbidden();
});

it('rejects keycloak bearer token on lead routes', function () {
    config(['api.keys' => []]);

    $response = $this->getJson(
        '/api/leads/1',
        ['Authorization' => 'Bearer test-token']
    );

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'This endpoint requires a valid X-API-KEY');
});
