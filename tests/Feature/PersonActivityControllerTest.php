<?php

namespace Tests\Feature;

use App\Enums\PatientMessageSenderType;
use App\Models\PatientMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('api.keys', ['valid-api-key-123']);
});

test('index returns patient messages for keycloak user', function () {
    // Arrange
    $keycloakId = 'test-keycloak-id';
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakId]);
    $user = User::factory()->create();

    $message = PatientMessage::create([
        'person_id'   => $person->id,
        'sender_type' => PatientMessageSenderType::STAFF,
        'sender_id'   => $user->id,
        'body'        => 'Test Body',
        'is_read'     => false,
        'activity_id' => null,
    ]);

    // Act
    $response = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/messages");

    // Assert
    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'person_id',
                'sender_type',
                'sender_id',
                'body',
                'is_read',
                'created_at',
                'sender',
            ],
        ],
    ]);

    $response->assertJsonFragment(['body' => 'Test Body']);
    $response->assertJsonFragment(['sender_type' => 'staff']);
});
