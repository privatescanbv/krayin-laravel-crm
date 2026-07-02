<?php

use App\Services\PatientPortal\PatientPortalPasswordResetTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Webkul\Contact\Models\Person;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('api.keys', ['valid-api-key-123']);
    Config::set('services.portal.patient.api_url', 'https://patient-portal.test');
    Config::set('services.portal.patient.api_token', 'portal-api-key');

    Http::fake(function (HttpRequest $request) {
        if ($request->url() === 'https://patient-portal.test/api/patient/password-reset/verify') {
            return Http::response(null, 204);
        }

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

    makeUser();
});

it('resets the password successfully when portal reset token is valid', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson("/api/patient/{$keycloakUserId}/password/reset", [
            'email'                 => 'patient@example.com',
            'reset_token'           => 'valid-reset-token',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ]);

    $response->assertNoContent();

    Http::assertSent(function (HttpRequest $request) use ($keycloakUserId) {
        return $request->url() === 'https://patient-portal.test/api/patient/password-reset/verify'
            && $request->hasHeader('X-API-KEY', 'portal-api-key')
            && ($request['email'] ?? null) === 'patient@example.com'
            && ($request['reset_token'] ?? null) === 'valid-reset-token'
            && ($request['keycloak_user_id'] ?? null) === $keycloakUserId;
    });
});

it('returns 422 when password_confirmation is missing', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson("/api/patient/{$keycloakUserId}/password/reset", [
            'email'       => 'patient@example.com',
            'reset_token' => 'valid-reset-token',
            'password'    => 'NieuwWachtwoord1!',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrorFor('password');
});

it('returns 422 when password and password_confirmation do not match', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson("/api/patient/{$keycloakUserId}/password/reset", [
            'email'                 => 'patient@example.com',
            'reset_token'           => 'valid-reset-token',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'AnderWachtwoord1!',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrorFor('password');
});

it('returns 404 for an unknown keycloak user id', function () {
    $unknownId = (string) Str::uuid();

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson('/api/patient/'.$unknownId.'/password/reset', [
            'email'                 => 'patient@example.com',
            'reset_token'           => 'valid-reset-token',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ]);

    $response->assertNotFound();
});

it('returns 403 when portal reset token verification fails', function () {
    $verifier = Mockery::mock(PatientPortalPasswordResetTokenVerifier::class);
    $verifier->shouldReceive('verify')
        ->once()
        ->with('patient@example.com', 'expired-reset-token', Mockery::type('string'))
        ->andReturn(false);
    $this->app->instance(PatientPortalPasswordResetTokenVerifier::class, $verifier);

    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson("/api/patient/{$keycloakUserId}/password/reset", [
            'email'                 => 'patient@example.com',
            'reset_token'           => 'expired-reset-token',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ]);

    $response->assertForbidden();
});

it('returns 403 when a patient Bearer token is combined with an API key', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders([
            'X-API-KEY'     => 'valid-api-key-123',
            'Authorization' => 'Bearer some-patient-token',
        ])
        ->postJson("/api/patient/{$keycloakUserId}/password/reset", [
            'email'                 => 'patient@example.com',
            'reset_token'           => 'valid-reset-token',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ]);

    $response->assertForbidden();
});
