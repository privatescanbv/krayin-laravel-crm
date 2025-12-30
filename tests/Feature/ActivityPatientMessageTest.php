<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\PatientMessageSenderType;
use App\Models\PatientMessage;
use Exception;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

beforeEach(function () {});

test('it_creates_a_patient_message_when_an_activity_of_type_patient_message_is_created', function () {
    // Arrange
    $user = User::factory()->create();
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();

    // Attach person to lead (workaround for Observer logic)
    try {
        $lead->persons()->attach($person->id);
        $lead->save();
        // Reload lead to ensure relation is loaded
        $lead->load('persons');
    } catch (Exception $e) {
        $lead->update(['contact_person_id' => $person->id]);
        $lead->refresh();
    }

    $this->actingAs($user);

    // Act
    $activity = Activity::create([
        'type'          => ActivityType::PATIENT_MESSAGE->value,
        'title'         => 'Test Message Subject',
        'comment'       => 'Test Message Body',
        'user_id'       => $user->id,
        'lead_id'       => $lead->id,
        'schedule_from' => now(),
        'schedule_to'   => now(),
        'is_done'       => 1,
    ]);

    // Assert
    $this->assertInstanceOf(Activity::class, $activity);

    $patientMessage = PatientMessage::where('activity_id', $activity->id)->first();

    $this->assertNotNull($patientMessage);
    $this->assertStringContainsString('Test Message Subject', $patientMessage->body);
    $this->assertStringContainsString('Test Message Body', $patientMessage->body);
    $this->assertEquals(PatientMessageSenderType::STAFF, $patientMessage->sender_type);
    $this->assertEquals($user->id, $patientMessage->sender_id);
    $this->assertEquals($person->id, $patientMessage->person_id);
});

test('it_does_not_create_a_patient_message_for_other_activity_types', function () {
    // Arrange
    $user = User::factory()->create();
    $this->actingAs($user);

    // Act
    $activity = Activity::create([
        'type'          => ActivityType::CALL->value,
        'title'         => 'Call Subject',
        'comment'       => 'Call Body',
        'user_id'       => $user->id,
        'schedule_from' => now(),
        'schedule_to'   => now(),
        'is_done'       => 1,
    ]);

    // Assert
    $patientMessage = PatientMessage::where('activity_id', $activity->id)->first();
    $this->assertNull($patientMessage);
});

test('it_allows_adding_message_via_api_as_patient', function () {
    // Arrange
    $keycloakUserId = 'test-keycloak-id-123';
    $apiKey = 'test-api-key';

    // Configure API key for the test
    config(['api.keys' => [$apiKey]]);

    // Create person with the keycloak ID
    $person = Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
    ]);

    // Act
    $response = $this->withHeaders([
        'X-API-KEY' => $apiKey,
    ])->postJson("/api/patient/{$keycloakUserId}/messages", [
        'body' => 'Message from patient via API',
    ]);

    // Assert
    $response->assertCreated();
    $response->assertJson(['message' => 'Message created successfully.']);

    $this->assertDatabaseHas('patient_messages', [
        'person_id'   => $person->id,
        'body'        => 'Message from patient via API',
        'sender_type' => PatientMessageSenderType::PATIENT->value,
    ]);
});
