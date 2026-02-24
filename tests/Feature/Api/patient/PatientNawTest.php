<?php

use App\Models\Address;
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

it('returns naw data for a patient without address', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'first_name'       => 'Jan',
        'last_name'        => 'Berg',
    ]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakUserId}/naw");

    $response->assertOk();
    $response->assertJsonStructure([
        'salutation', 'first_name', 'lastname_prefix', 'last_name',
        'married_name_prefix', 'married_name', 'initials', 'date_of_birth',
        'gender', 'phones', 'emails', 'address',
    ]);
    $response->assertJsonPath('first_name', 'Jan');
    $response->assertJsonPath('last_name', 'Berg');
    $response->assertJsonPath('address', null);
});

it('returns naw data including address', function () {
    $keycloakUserId = (string) Str::uuid();
    $address = Address::factory()->create([
        'street'       => 'Hoofdstraat',
        'house_number' => '10',
        'postal_code'  => '1234AB',
        'city'         => 'Amsterdam',
    ]);
    Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'address_id'       => $address->id,
    ]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakUserId}/naw");

    $response->assertOk();
    $response->assertJsonPath('address.street', 'Hoofdstraat');
    $response->assertJsonPath('address.house_number', '10');
    $response->assertJsonPath('address.city', 'Amsterdam');
});

it('returns 404 for unknown patient on get', function () {
    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson('/api/patient/non-existent-id/naw');

    $response->assertNotFound();
});

it('can update person name fields', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'first_name'      => 'Pieter',
            'lastname_prefix' => 'van',
            'last_name'       => 'Dijk',
            'initials'        => 'P.',
        ]);

    $response->assertOk();
    $response->assertJsonPath('first_name', 'Pieter');
    $response->assertJsonPath('lastname_prefix', 'van');
    $response->assertJsonPath('last_name', 'Dijk');
    $response->assertJsonPath('initials', 'P.');

    $person->refresh();
    expect($person->first_name)->toBe('Pieter')
        ->and($person->last_name)->toBe('Dijk');
});

it('can update salutation and gender', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'salutation' => 'Dhr.',
            'gender'     => 'Man',
        ]);

    $response->assertOk();
    $response->assertJsonPath('salutation', 'Dhr.');
    $response->assertJsonPath('gender', 'Man');

    $person->refresh();
    expect($person->salutation->value)->toBe('Dhr.');
    expect($person->gender->value)->toBe('Man');
});

it('can update date of birth', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'date_of_birth' => '1990-06-15',
        ]);

    $response->assertOk();
    $response->assertJsonPath('date_of_birth', '1990-06-15');

    $person->refresh();
    expect($person->date_of_birth->format('Y-m-d'))->toBe('1990-06-15');
});

it('creates a new address when person has none', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    expect($person->address_id)->toBeNull();

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'address' => [
                'street'       => 'Kerkstraat',
                'house_number' => '5',
                'postal_code'  => '5678CD',
                'city'         => 'Utrecht',
            ],
        ]);

    $response->assertOk();
    $response->assertJsonPath('address.street', 'Kerkstraat');
    $response->assertJsonPath('address.city', 'Utrecht');

    $person->refresh();
    expect($person->address_id)->not->toBeNull()
        ->and($person->address->city)->toBe('Utrecht');
});

it('updates existing address when person already has one', function () {
    $keycloakUserId = (string) Str::uuid();
    $address = Address::factory()->create(['city' => 'Rotterdam']);
    $person = Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'address_id'       => $address->id,
    ]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'address' => [
                'city' => 'Den Haag',
            ],
        ]);

    $response->assertOk();
    $response->assertJsonPath('address.city', 'Den Haag');

    $address->refresh();
    expect($address->city)->toBe('Den Haag');
    // address_id should remain the same
    expect($person->fresh()->address_id)->toBe($address->id);
});

it('returns 422 for invalid salutation', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'salutation' => 'Meneer',
        ]);

    $response->assertUnprocessable();
});

it('returns 422 for invalid gender', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'gender' => 'Onbekend',
        ]);

    $response->assertUnprocessable();
});

it('returns 422 for invalid email in emails array', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'emails' => [
                ['value' => 'not-an-email', 'label' => 'eigen', 'is_default' => true],
            ],
        ]);

    $response->assertUnprocessable();
});

it('returns 404 for unknown patient on update', function () {
    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson('/api/patient/non-existent-id/naw', [
            'first_name' => 'Jan',
        ]);

    $response->assertNotFound();
});

it('can update multiple phones and returns them in response', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $phones = [
        ['value' => '+31612345678', 'label' => 'eigen',   'is_default' => true],
        ['value' => '+31201234567', 'label' => 'relatie', 'is_default' => false],
    ];

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", compact('phones'));

    $response->assertOk();
    $response->assertJsonCount(2, 'phones');
    $response->assertJsonPath('phones.0.value', '+31612345678');
    $response->assertJsonPath('phones.0.label', 'eigen');
    $response->assertJsonPath('phones.0.is_default', true);
    $response->assertJsonPath('phones.1.value', '+31201234567');
    $response->assertJsonPath('phones.1.label', 'relatie');
    $response->assertJsonPath('phones.1.is_default', false);
});

it('can update multiple emails and returns them in response', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $emails = [
        ['value' => 'jan@example.com',  'label' => 'eigen',   'is_default' => true],
        ['value' => 'jan@werkgever.nl', 'label' => 'relatie', 'is_default' => false],
    ];

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", compact('emails'));

    $response->assertOk();
    $response->assertJsonCount(2, 'emails');
    $response->assertJsonPath('emails.0.value', 'jan@example.com');
    $response->assertJsonPath('emails.0.label', 'eigen');
    $response->assertJsonPath('emails.0.is_default', true);
    $response->assertJsonPath('emails.1.value', 'jan@werkgever.nl');
    $response->assertJsonPath('emails.1.label', 'relatie');
    $response->assertJsonPath('emails.1.is_default', false);
});

it('returns stored phones and emails in get response', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'phones'           => [
            ['value' => '+31612345678', 'label' => 'eigen', 'is_default' => true],
        ],
        'emails'           => [
            ['value' => 'jan@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
    ]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakUserId}/naw");

    $response->assertOk();
    $response->assertJsonCount(1, 'phones');
    $response->assertJsonPath('phones.0.value', '+31612345678');
    $response->assertJsonPath('phones.0.label', 'eigen');
    $response->assertJsonPath('phones.0.is_default', true);
    $response->assertJsonCount(1, 'emails');
    $response->assertJsonPath('emails.0.value', 'jan@example.com');
    $response->assertJsonPath('emails.0.label', 'eigen');
    $response->assertJsonPath('emails.0.is_default', true);
});

it('returns 422 when a phone is missing its label', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'phones' => [
                ['value' => '+31612345678', 'is_default' => true],
            ],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['phones.0.label']);
});

it('returns 422 when an email is missing its label', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'emails' => [
                ['value' => 'jan@example.com', 'is_default' => true],
            ],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['emails.0.label']);
});

it('returns 422 for an invalid phone label', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'phones' => [
                ['value' => '+31612345678', 'label' => 'mobiel', 'is_default' => true],
            ],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['phones.0.label']);
});

it('returns 422 when a phone does not start with plus (to match admin validation)', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'phones' => [
                ['value' => '0635354545', 'label' => 'eigen', 'is_default' => true],
            ],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['phones.0.value']);
});

it('returns 422 for an invalid email label', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakUserId}/naw", [
            'emails' => [
                ['value' => 'jan@example.com', 'label' => 'werk', 'is_default' => true],
            ],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['emails.0.label']);
});
