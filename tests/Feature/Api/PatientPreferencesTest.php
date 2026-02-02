<?php

use App\Enums\PersonPreferenceKey;
use App\Models\PersonPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Webkul\Contact\Models\Person;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('api.keys', ['valid-api-key-123']);
});

it('returns default preferences when no preferences are stored', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakUserId}/preferences");

    $response->assertOk();
    $response->assertJsonStructure([
        'preferences' => [
            'email_notifications_enabled' => [
                'value',
                'is_system_managed',
            ],
        ],
    ]);
    $response->assertJsonPath('preferences.email_notifications_enabled.value', true);
    $response->assertJsonPath('preferences.email_notifications_enabled.is_system_managed', false);
});

it('returns stored preferences when they exist', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    PersonPreference::setValueForPerson(
        $person->id,
        PersonPreferenceKey::EMAIL_NOTIFICATIONS_ENABLED,
        false
    );

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakUserId}/preferences");

    $response->assertOk();
    $response->assertJsonPath('preferences.email_notifications_enabled.value', false);
});

it('can update preferences', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/preferences", [
            'preferences' => [
                'email_notifications_enabled' => false,
            ],
        ]);

    $response->assertOk();
    $response->assertJsonPath('preferences.email_notifications_enabled.value', false);

    // Verify it was stored
    $stored = PersonPreference::where('person_id', $person->id)
        ->where('key', PersonPreferenceKey::EMAIL_NOTIFICATIONS_ENABLED->value)
        ->first();

    expect($stored)->not->toBeNull();
    expect($stored->typed_value)->toBeFalse();
});

it('returns 404 for unknown patient on update', function () {
    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson('/api/patient/non-existent-id/preferences', [
            'preferences' => [
                'email_notifications_enabled' => false,
            ],
        ]);

    $response->assertNotFound();
});
