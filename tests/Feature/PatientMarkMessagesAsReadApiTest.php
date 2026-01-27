<?php

namespace Tests\Feature;

use App\Enums\PatientMessageSenderType;
use App\Models\PatientMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Webkul\Contact\Models\Person;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('api.keys', ['valid-api-key-123']);
});

test('mark_as_read returns response body with marked_count', function () {
    $keycloakId = 'kc-user-mark-read-1';
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakId]);

    // Staff message should be marked as read
    PatientMessage::create([
        'person_id'   => $person->id,
        'sender_type' => PatientMessageSenderType::STAFF,
        'sender_id'   => null,
        'body'        => 'Staff message',
        'is_read'     => false,
        'activity_id' => null,
    ]);

    // Patient message should NOT be marked as read by patient action
    PatientMessage::create([
        'person_id'   => $person->id,
        'sender_type' => PatientMessageSenderType::PATIENT,
        'sender_id'   => null,
        'body'        => 'Patient message',
        'is_read'     => false,
        'activity_id' => null,
    ]);

    $response = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson("/api/patient/{$keycloakId}/messages/mark_as_read");

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data' => [
            'marked_count',
        ],
    ]);

    $response->assertJsonPath('data.marked_count', 1);
});
