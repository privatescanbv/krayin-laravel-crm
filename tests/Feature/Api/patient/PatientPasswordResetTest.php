<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Webkul\Contact\Models\Person;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('api.keys', ['valid-api-key-123']);

    // Mock Keycloak HTTP calls triggered by PersonObserver on password save.
    Http::fake(function (HttpRequest $request) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

        if ($path === '/realms/master/protocol/openid-connect/token') {
            return Http::response(['access_token' => 'test-admin-token'], 200);
        }

        if (preg_match('#^/admin/realms/[^/]+/users/[^/]+/reset-password$#', $path)) {
            return Http::response(null, 204);
        }

        if (preg_match('#^/admin/realms/[^/]+/users/[^/]+$#', $path)) {
            return Http::response(['id' => basename($path)], 200);
        }

        return Http::response([], 200);
    });

    // PersonObserver needs a user_id for activity logging.
    makeUser();
});

it('resets the password successfully when password_confirmation matches', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);
    Cache::put('patient_reset_pending:'.$keycloakUserId, true, now()->addHours(2));

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson("/api/patient/{$keycloakUserId}/password/reset", [
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ]);

    $response->assertNoContent();
});

it('returns 422 when password_confirmation is missing', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);
    Cache::put('patient_reset_pending:'.$keycloakUserId, true, now()->addHours(2));

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson("/api/patient/{$keycloakUserId}/password/reset", [
            'password' => 'NieuwWachtwoord1!',
            // password_confirmation ontbreekt — zoals de portal momenteel verstuurt
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrorFor('password');
});

it('returns 422 when password and password_confirmation do not match', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);
    Cache::put('patient_reset_pending:'.$keycloakUserId, true, now()->addHours(2));

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson("/api/patient/{$keycloakUserId}/password/reset", [
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'AnderWachtwoord1!',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrorFor('password');
});

it('returns 404 for an unknown keycloak user id', function () {
    $unknownId = (string) Str::uuid();
    Cache::put('patient_reset_pending:'.$unknownId, true, now()->addHours(2));

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson('/api/patient/'.$unknownId.'/password/reset', [
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ]);

    $response->assertNotFound();
});

it('returns 403 when a patient Bearer token is combined with an API key', function () {
    // Security: a patient must not bypass current_password by calling the reset endpoint
    // with their own Bearer token. Even when paired with a valid API key, the controller
    // must refuse Bearer token callers so only service-to-service (API key only) is allowed.
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders([
            'X-API-KEY'     => 'valid-api-key-123',
            'Authorization' => 'Bearer some-patient-token',
        ])
        ->postJson("/api/patient/{$keycloakUserId}/password/reset", [
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ]);

    $response->assertForbidden();
});
