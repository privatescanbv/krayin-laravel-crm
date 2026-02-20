<?php

use App\Enums\NotificationReferenceType;
use App\Enums\PreferredLanguage;
use App\Events\PatientNotifyEvent;
use App\Listeners\CreatePatientNotification;
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
        'dismissable'     => true,
        'title'           => 'Nieuw document beschikbaar',
        'summary'         => 'Samenvatting',
        'reference_type'  => NotificationReferenceType::FILE,
        'reference_id'    => 123,
        'read_at'         => null,
        'dismissed_at'    => null,
        'expires_at'      => null,
    ]);

    /** @var PatientNotification $n2 */
    $n2 = PatientNotification::query()->create([
        'patient_id'      => $person->id,
        'dismissable'     => false,
        'title'           => 'Document geüpdatet',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::FILE,
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
        'dismissable'     => true,
        'title'           => 'Active',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::FILE,
        'reference_id'    => 1,
        'read_at'         => null,
        'dismissed_at'    => null,
        'expires_at'      => $now->copy()->addDay(),
    ]);

    // Dismissed
    PatientNotification::query()->create([
        'patient_id'      => $person->id,
        'dismissable'     => true,
        'title'           => 'Dismissed',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::FILE,
        'reference_id'    => 2,
        'read_at'         => null,
        'dismissed_at'    => $now->copy()->subMinute(),
        'expires_at'      => null,
    ]);

    // Expired
    PatientNotification::query()->create([
        'patient_id'      => $person->id,
        'dismissable'     => true,
        'title'           => 'Expired',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::FILE,
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
        'dismissable'     => true,
        'title'           => 'Dismiss me',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::FILE,
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
        'dismissable'     => false,
        'title'           => 'Persistent',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::FILE,
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
        'dismissable'     => true,
        'title'           => 'A only',
        'summary'         => null,
        'reference_type'  => NotificationReferenceType::FILE,
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

// ── Listener behaviour tests ────────────────────────────────────────────────

it('creates a new FILE notification when none exists', function () {
    $person = Person::factory()->create();

    $event = new PatientNotifyEvent(
        patientId: $person->id,
        entityName: 'rapport.pdf',
        referenceType: NotificationReferenceType::FILE,
        referenceId: 1,
        dismissable: true,
    );
    (new CreatePatientNotification)->handle($event);

    $notifications = PatientNotification::where('patient_id', $person->id)->get();
    expect($notifications)->toHaveCount(1);

    $n = $notifications->first();
    expect($n->title)->toBe('patient_notifications.file.title')
        ->and($n->summary)->toBe('patient_notifications.file.summary')
        ->and($n->entity_names)->toBe(['rapport.pdf']);
});

it('appends entityName to existing active FILE notification instead of creating a new row', function () {
    $person = Person::factory()->create();

    PatientNotification::query()->create([
        'patient_id'     => $person->id,
        'dismissable'    => true,
        'title'          => 'patient_notifications.file.title',
        'summary'        => 'patient_notifications.file.summary',
        'entity_names'   => ['eerste.pdf'],
        'reference_type' => NotificationReferenceType::FILE,
        'reference_id'   => 1,
        'dismissed_at'   => null,
        'expires_at'     => null,
    ]);

    $event = new PatientNotifyEvent(
        patientId: $person->id,
        entityName: 'tweede.pdf',
        referenceType: NotificationReferenceType::FILE,
        referenceId: 2,
        dismissable: true,
    );
    (new CreatePatientNotification)->handle($event);

    $notifications = PatientNotification::where('patient_id', $person->id)->get();
    expect($notifications)->toHaveCount(1);

    $updated = $notifications->first();
    expect($updated->summary)->toBe('patient_notifications.file.summary')
        ->and($updated->entity_names)->toBe(['eerste.pdf', 'tweede.pdf']);
});

it('creates a new FILE notification when existing one is dismissed', function () {
    $now = Carbon::now();
    $person = Person::factory()->create();

    PatientNotification::query()->create([
        'patient_id'     => $person->id,
        'dismissable'    => true,
        'title'          => 'patient_notifications.file.title',
        'summary'        => 'patient_notifications.file.summary',
        'entity_names'   => ['oud.pdf'],
        'reference_type' => NotificationReferenceType::FILE,
        'reference_id'   => 1,
        'dismissed_at'   => $now->copy()->subMinute(),
        'expires_at'     => null,
    ]);

    $event = new PatientNotifyEvent(
        patientId: $person->id,
        entityName: 'nieuw.pdf',
        referenceType: NotificationReferenceType::FILE,
        referenceId: 2,
        dismissable: true,
    );
    (new CreatePatientNotification)->handle($event);

    expect(PatientNotification::where('patient_id', $person->id)->count())->toBe(2);

    $new = PatientNotification::where('patient_id', $person->id)->orderByDesc('id')->first();
    expect($new->entity_names)->toBe(['nieuw.pdf']);
});

it('creates a new FILE notification when existing one is expired', function () {
    $now = Carbon::now();
    $person = Person::factory()->create();

    PatientNotification::query()->create([
        'patient_id'     => $person->id,
        'dismissable'    => true,
        'title'          => 'patient_notifications.file.title',
        'summary'        => 'patient_notifications.file.summary',
        'entity_names'   => ['oud.pdf'],
        'reference_type' => NotificationReferenceType::FILE,
        'reference_id'   => 1,
        'dismissed_at'   => null,
        'expires_at'     => $now->copy()->subSecond(),
    ]);

    $event = new PatientNotifyEvent(
        patientId: $person->id,
        entityName: 'nieuw.pdf',
        referenceType: NotificationReferenceType::FILE,
        referenceId: 2,
        dismissable: true,
    );
    (new CreatePatientNotification)->handle($event);

    expect(PatientNotification::where('patient_id', $person->id)->count())->toBe(2);

    $new = PatientNotification::where('patient_id', $person->id)->orderByDesc('id')->first();
    expect($new->entity_names)->toBe(['nieuw.pdf']);
});

it('creates a new GVL_FORM notification when none exists', function () {
    $person = Person::factory()->create();

    $entityName = 'https://forms.example.com/abc';
    $event = new PatientNotifyEvent(
        patientId: $person->id,
        entityName: $entityName,
        referenceType: NotificationReferenceType::GVL_FORM,
        referenceId: 99,
        dismissable: false,
    );
    (new CreatePatientNotification)->handle($event);

    $notifications = PatientNotification::where('patient_id', $person->id)->get();
    expect($notifications)->toHaveCount(1);

    $n = $notifications->first();
    expect($n->title)->toBe('patient_notifications.gvl.title')
        ->and($n->summary)->toBe('patient_notifications.gvl.summary')
        ->and($n->entity_names)->toBe([$entityName]);
});

it('merges into existing active GVL_FORM notification instead of creating a new row', function () {
    $person = Person::factory()->create();

    $firstName = 'https://forms.example.com/first';
    $secondName = 'https://forms.example.com/second';

    PatientNotification::query()->create([
        'patient_id'     => $person->id,
        'dismissable'    => false,
        'title'          => 'patient_notifications.gvl.title',
        'summary'        => 'patient_notifications.gvl.summary',
        'entity_names'   => [$firstName],
        'reference_type' => NotificationReferenceType::GVL_FORM,
        'reference_id'   => 10,
        'dismissed_at'   => null,
        'expires_at'     => null,
    ]);

    $event = new PatientNotifyEvent(
        patientId: $person->id,
        entityName: $secondName,
        referenceType: NotificationReferenceType::GVL_FORM,
        referenceId: 20,
        dismissable: false,
    );
    (new CreatePatientNotification)->handle($event);

    $notifications = PatientNotification::where('patient_id', $person->id)->get();
    expect($notifications)->toHaveCount(1);

    $updated = $notifications->first();
    expect($updated->reference_id)->toBe(20)
        ->and($updated->entity_names)->toBe([$firstName, $secondName]);
});

it('creates a new GVL_FORM notification when existing one is dismissed', function () {
    $now = Carbon::now();
    $person = Person::factory()->create();

    PatientNotification::query()->create([
        'patient_id'     => $person->id,
        'dismissable'    => false,
        'title'          => 'patient_notifications.gvl.title',
        'summary'        => 'patient_notifications.gvl.summary',
        'entity_names'   => ['https://forms.example.com/old'],
        'reference_type' => NotificationReferenceType::GVL_FORM,
        'reference_id'   => 10,
        'dismissed_at'   => $now->copy()->subMinute(),
        'expires_at'     => null,
    ]);

    $newName = 'https://forms.example.com/new';
    $event = new PatientNotifyEvent(
        patientId: $person->id,
        entityName: $newName,
        referenceType: NotificationReferenceType::GVL_FORM,
        referenceId: 20,
        dismissable: false,
    );
    (new CreatePatientNotification)->handle($event);

    expect(PatientNotification::where('patient_id', $person->id)->count())->toBe(2);

    $new = PatientNotification::where('patient_id', $person->id)->orderByDesc('id')->first();
    expect($new->entity_names)->toBe([$newName]);
});

it('creates a new GVL_FORM notification when existing one is expired', function () {
    $now = Carbon::now();
    $person = Person::factory()->create();

    PatientNotification::query()->create([
        'patient_id'     => $person->id,
        'dismissable'    => false,
        'title'          => 'patient_notifications.gvl.title',
        'summary'        => 'patient_notifications.gvl.summary',
        'entity_names'   => ['https://forms.example.com/old'],
        'reference_type' => NotificationReferenceType::GVL_FORM,
        'reference_id'   => 10,
        'dismissed_at'   => null,
        'expires_at'     => $now->copy()->subSecond(),
    ]);

    $newName = 'https://forms.example.com/new';
    $event = new PatientNotifyEvent(
        patientId: $person->id,
        entityName: $newName,
        referenceType: NotificationReferenceType::GVL_FORM,
        referenceId: 20,
        dismissable: false,
    );
    (new CreatePatientNotification)->handle($event);

    expect(PatientNotification::where('patient_id', $person->id)->count())->toBe(2);

    $new = PatientNotification::where('patient_id', $person->id)->orderByDesc('id')->first();
    expect($new->entity_names)->toBe([$newName]);
});

// ── API localisation tests ───────────────────────────────────────────────────

it('returns Dutch translated strings when person has no preferred_language', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create([
        'keycloak_user_id'    => $keycloakUserId,
        'preferred_language'  => null,
    ]);

    PatientNotification::query()->create([
        'patient_id'     => $person->id,
        'dismissable'    => true,
        'title'          => 'patient_notifications.file.title',
        'summary'        => 'patient_notifications.file.summary',
        'entity_names'   => ['rapport.pdf', 'factuur.pdf'],
        'reference_type' => NotificationReferenceType::FILE,
        'reference_id'   => 1,
        'dismissed_at'   => null,
        'expires_at'     => null,
    ]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakUserId}/notifications");

    $response->assertOk();
    $response->assertJsonPath('data.0.title', 'Document beschikbaar');
    $response->assertJsonPath('data.0.summary', 'De volgende documenten staan voor u klaar: rapport.pdf, factuur.pdf');
});

it('returns English translated strings when person has preferred_language en', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create([
        'keycloak_user_id'   => $keycloakUserId,
        'preferred_language' => PreferredLanguage::EN,
    ]);

    PatientNotification::query()->create([
        'patient_id'     => $person->id,
        'dismissable'    => true,
        'title'          => 'patient_notifications.file.title',
        'summary'        => 'patient_notifications.file.summary',
        'entity_names'   => ['report.pdf'],
        'reference_type' => NotificationReferenceType::FILE,
        'reference_id'   => 1,
        'dismissed_at'   => null,
        'expires_at'     => null,
    ]);

    $response = $this
        ->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakUserId}/notifications");

    $response->assertOk();
    $response->assertJsonPath('data.0.title', 'Document available');
    $response->assertJsonPath('data.0.summary', 'The following documents are ready for you: report.pdf');
});
