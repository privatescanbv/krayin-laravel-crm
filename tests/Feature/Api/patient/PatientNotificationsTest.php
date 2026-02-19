<?php

use App\Enums\NotificationReferenceType;
use App\Models\PatientNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Webkul\Contact\Models\Person;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('api.keys', ['valid-api-key-123']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns paginated notifications for a patient and sets read_at when returning them', function () {
    $now = Carbon::create(2026, 2, 2, 12, 0, 0);
    Carbon::setTestNow($now);

    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    /** @var PatientNotification $n1 */
    $n1 = PatientNotification::query()->create([
        'patient_id'      => $person->id,
        'type'            => 'document',
        'dismissable'     => true,
        'title'           => 'Nieuw document beschikbaar',
        'summary'         => 'Samenvatting',
        'reference_type'  => NotificationReferenceType::ACTIVITY,
        'reference_id'    => 123,
        'read_at'         => null,
        'dismissed_at'    => null,
        'expires_at'      => null,
    ]);

    /** @var PatientNotification $n2 */
    $n2 = PatientNotification::query()->create([
        'patient_id'      => $person->id,
        'type'            => 'document',
        'dismissable'     => false,
        'title'           => 'Document geüpdatet',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::ACTIVITY,
        'reference_id'    => 456,
        'read_at'         => null,
        'dismissed_at'    => null,
        'expires_at'      => null,
    ]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakUserId}/notifications");

    $response->assertOk();

    $response->assertJsonStructure([
        'data' => [
            [
                'id',
                'type',
                'dismissable',
                'title',
                'summary',
                'reference' => [
                    'type',
                    'id',
                ],
                'created_at',
            ],
        ],
        'meta' => [
            'current_page',
            'per_page',
            'total',
        ],
    ]);

    $response->assertJsonPath('meta.current_page', 1);
    $response->assertJsonPath('meta.total', 2);

    // Both records should have been marked as read when returned.
    $n1->refresh();
    $n2->refresh();
    expect($n1->read_at)->not->toBeNull()
        ->and($n2->read_at)->not->toBeNull()
        ->and($n1->read_at->eq($now))->toBeTrue()
        ->and($n2->read_at->eq($now))->toBeTrue();
});

it('does not return dismissed or expired notifications', function () {
    $now = Carbon::create(2026, 2, 2, 12, 0, 0);
    Carbon::setTestNow($now);

    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    // Active
    PatientNotification::query()->create([
        'patient_id'      => $person->id,
        'type'            => 'document',
        'dismissable'     => true,
        'title'           => 'Active',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::ACTIVITY,
        'reference_id'    => 1,
        'read_at'         => null,
        'dismissed_at'    => null,
        'expires_at'      => $now->copy()->addDay(),
    ]);

    // Dismissed
    PatientNotification::query()->create([
        'patient_id'      => $person->id,
        'type'            => 'document',
        'dismissable'     => true,
        'title'           => 'Dismissed',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::ACTIVITY,
        'reference_id'    => 2,
        'read_at'         => null,
        'dismissed_at'    => $now->copy()->subMinute(),
        'expires_at'      => null,
    ]);

    // Expired
    PatientNotification::query()->create([
        'patient_id'      => $person->id,
        'type'            => 'document',
        'dismissable'     => true,
        'title'           => 'Expired',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::ACTIVITY,
        'reference_id'    => 3,
        'read_at'         => null,
        'dismissed_at'    => null,
        'expires_at'      => $now->copy()->subSecond(),
    ]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakUserId}/notifications");

    $response->assertOk();
    $response->assertJsonPath('meta.total', 1);
    $response->assertJsonPath('data.0.title', 'Active');
});

it('marks a dismissable notification as dismissed via dismissed_at', function () {
    $now = Carbon::create(2026, 2, 2, 12, 0, 0);
    Carbon::setTestNow($now);

    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    /** @var PatientNotification $notification */
    $notification = PatientNotification::query()->create([
        'patient_id'      => $person->id,
        'type'            => 'document',
        'dismissable'     => true,
        'title'           => 'Dismiss me',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::ACTIVITY,
        'reference_id'    => 10,
        'read_at'         => null,
        'dismissed_at'    => null,
        'expires_at'      => null,
    ]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson("/api/patient/{$keycloakUserId}/notifications/{$notification->id}/read");

    $response->assertNoContent();

    $notification->refresh();
    expect($notification->dismissed_at)->not->toBeNull()
        ->and($notification->dismissed_at->eq($now))->toBeTrue()
        ->and($notification->read_at)->not->toBeNull()
        ->and($notification->read_at->eq($now))->toBeTrue();
});

it('returns 422 when trying to dismiss a non-dismissable notification', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    /** @var PatientNotification $notification */
    $notification = PatientNotification::query()->create([
        'patient_id'      => $person->id,
        'type'            => 'document',
        'dismissable'     => false,
        'title'           => 'Persistent',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::ACTIVITY,
        'reference_id'    => 11,
        'read_at'         => null,
        'dismissed_at'    => null,
        'expires_at'      => null,
    ]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson("/api/patient/{$keycloakUserId}/notifications/{$notification->id}/read");

    $response->assertStatus(422);

    $notification->refresh();
    expect($notification->dismissed_at)->toBeNull();
});

it('returns 404 when trying to dismiss a notification of another patient', function () {
    $patientAKeycloakUserId = (string) Str::uuid();
    $patientBKeycloakUserId = (string) Str::uuid();

    $patientA = Person::factory()->create(['keycloak_user_id' => $patientAKeycloakUserId]);
    Person::factory()->create(['keycloak_user_id' => $patientBKeycloakUserId]);

    /** @var PatientNotification $notification */
    $notification = PatientNotification::query()->create([
        'patient_id'      => $patientA->id,
        'type'            => 'document',
        'dismissable'     => true,
        'title'           => 'A only',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::ACTIVITY,
        'reference_id'    => 12,
        'read_at'         => null,
        'dismissed_at'    => null,
        'expires_at'      => null,
    ]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->postJson("/api/patient/{$patientBKeycloakUserId}/notifications/{$notification->id}/read");

    $response->assertNotFound();
});
