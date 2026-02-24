<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Webkul\Contact\Models\Person;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('api.keys', ['valid-api-key-123']);
    // The PersonObserver falls back to user_id=1 when no user is authenticated.
    // Create a user so that FK constraint on activities.user_id is satisfied.
    makeUser();
});

it('successfully changes the password', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);
    $person->password = 'OudWachtwoord1!';
    $person->save();

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/password", [
            'current_password'      => 'OudWachtwoord1!',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ]);

    $response->assertNoContent();

    $person->refresh();
    expect($person->getDecryptedPassword())->toBe('NieuwWachtwoord1!');
});

it('returns 422 for incorrect current password', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);
    $person->password = 'CorrectWachtwoord1!';
    $person->save();

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/password", [
            'current_password'      => 'FoutWachtwoord!',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonPath('errors.current_password.0', 'Het huidige wachtwoord is onjuist.');
});

it('returns 422 when new password is too short', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);
    $person->password = 'HuidigWachtwoord1!';
    $person->save();

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/password", [
            'current_password'      => 'HuidigWachtwoord1!',
            'password'              => 'kort',
            'password_confirmation' => 'kort',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['password']);
});

it('returns 422 when password confirmation does not match', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);
    $person->password = 'HuidigWachtwoord1!';
    $person->save();

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/password", [
            'current_password'      => 'HuidigWachtwoord1!',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'AndereWachtwoord1!',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['password']);
});

it('allows setting a password when person has none yet', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'password'         => null,
    ]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/password", [
            'current_password'      => 'anything',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ]);

    $response->assertNoContent();

    $person = Person::where('keycloak_user_id', $keycloakUserId)->first();
    expect($person->getDecryptedPassword())->toBe('NieuwWachtwoord1!');
});

it('returns 404 for unknown patient', function () {
    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson('/api/patient/non-existent-id/password', [
            'current_password'      => 'OudWachtwoord1!',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ]);

    $response->assertNotFound();
});
