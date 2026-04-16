<?php

use App\Enums\EmailTemplateCode;
use App\Enums\NotificationReferenceType;
use App\Events\PatientNotifyEvent;
use App\Models\PatientNotification;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Webkul\Contact\Models\Person;
use Webkul\Email\Mails\Email as EmailMailable;
use Webkul\Email\Models\Email;
use Webkul\EmailTemplate\Models\EmailTemplate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    config([
        'mail.send_only_accept'                                 => '*@example.com',
        'services.portal.patient.notify_email_interval_minutes' => 30,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('first PatientNotifyEvent schedules digest at now plus interval', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-16 10:00:00', 'Europe/Amsterdam'));

    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    PatientNotifyEvent::dispatch(
        $person->id,
        'doc.pdf',
        NotificationReferenceType::FILE,
        1,
        true,
    );

    $person->refresh();

    expect($person->patient_portal_notify_scheduled_at)->not->toBeNull()
        ->and($person->patient_portal_notify_scheduled_at->format('Y-m-d H:i'))->toBe('2026-04-16 10:30');
});

test('second PatientNotifyEvent before due does not change scheduled time', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-16 10:00:00', 'Europe/Amsterdam'));

    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    PatientNotifyEvent::dispatch($person->id, 'a.pdf', NotificationReferenceType::FILE, 1, true);
    $person->refresh();
    $first = $person->patient_portal_notify_scheduled_at?->copy();

    Carbon::setTestNow(Carbon::parse('2026-04-16 10:15:00', 'Europe/Amsterdam'));

    PatientNotifyEvent::dispatch($person->id, 'b.pdf', NotificationReferenceType::FILE, 2, true);
    $person->refresh();

    expect($person->patient_portal_notify_scheduled_at?->equalTo($first))->toBeTrue();
});

test('delay runs from first notification: events at 13:00 and 13:30 yield send at 15:00 when interval is 120 minutes', function () {
    config(['services.portal.patient.notify_email_interval_minutes' => 120]);

    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    Carbon::setTestNow(Carbon::parse('2026-06-01 13:00:00', 'Europe/Amsterdam'));
    PatientNotifyEvent::dispatch($person->id, 'eerste.pdf', NotificationReferenceType::FILE, 1, true);
    $person->refresh();
    expect($person->patient_portal_notify_scheduled_at?->format('Y-m-d H:i'))->toBe('2026-06-01 15:00');

    Carbon::setTestNow(Carbon::parse('2026-06-01 13:30:00', 'Europe/Amsterdam'));
    PatientNotifyEvent::dispatch($person->id, 'tweede.pdf', NotificationReferenceType::FILE, 2, true);
    $person->refresh();
    expect($person->patient_portal_notify_scheduled_at?->format('Y-m-d H:i'))->toBe('2026-06-01 15:00');
});

test('event after last digest schedules at last_sent plus interval when still in cooldown', function () {
    config(['services.portal.patient.notify_email_interval_minutes' => 120]);

    Carbon::setTestNow(Carbon::parse('2026-04-16 10:30:00', 'Europe/Amsterdam'));

    $person = Person::factory()->create([
        'emails'                              => [['value' => 'patient@example.com', 'is_default' => true]],
        'patient_portal_last_notify_email_at' => Carbon::parse('2026-04-16 10:00:00', 'Europe/Amsterdam'),
    ]);

    PatientNotification::factory()->create([
        'patient_id'                => $person->id,
        'dismissed_at'              => null,
        'last_notified_by_email_at' => null,
    ]);

    PatientNotifyEvent::dispatch($person->id, 'new.pdf', NotificationReferenceType::FILE, 9, true);
    $person->refresh();

    expect($person->patient_portal_notify_scheduled_at?->format('Y-m-d H:i'))->toBe('2026-04-16 12:00');
});

test('send command emails due digest and clears schedule using new content template', function () {
    Mail::fake();

    Carbon::setTestNow(Carbon::parse('2026-04-16 14:00:00', 'Europe/Amsterdam'));

    $person = Person::factory()->create([
        'last_name' => 'Jansen',
        'emails'    => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    PatientNotification::factory()->count(2)->create([
        'patient_id'                => $person->id,
        'dismissed_at'              => null,
        'last_notified_by_email_at' => null,
    ]);

    $person->forceFill([
        'patient_portal_notify_scheduled_at' => Carbon::parse('2026-04-16 13:00:00', 'Europe/Amsterdam'),
    ])->save();

    $this->artisan('patient:send-notification-email')->assertExitCode(0);

    expect(Email::where('person_id', $person->id)->count())->toBe(1);

    $updatedCount = PatientNotification::query()
        ->where('patient_id', $person->id)
        ->whereNotNull('last_notified_by_email_at')
        ->count();

    expect($updatedCount)->toBe(2);

    $person->refresh();
    expect($person->patient_portal_notify_scheduled_at)->toBeNull()
        ->and($person->patient_portal_last_notify_email_at)->not->toBeNull();

    Mail::assertQueued(EmailMailable::class, 1);
});

test('second notification after first email triggers a second digest email after cooldown', function () {
    Mail::fake();
    config(['services.portal.patient.notify_email_interval_minutes' => 120]);

    $firstSentAt = Carbon::parse('2026-04-16 10:00:00', 'Europe/Amsterdam');
    Carbon::setTestNow($firstSentAt);

    $person = Person::factory()->create([
        'last_name'                           => 'Jansen',
        'emails'                              => [['value' => 'patient@example.com', 'is_default' => true]],
        'patient_portal_last_notify_email_at' => $firstSentAt,
        'patient_portal_notify_scheduled_at'  => null,
    ]);

    // Old notification already notified (part of the first email batch)
    PatientNotification::factory()->create([
        'patient_id'                => $person->id,
        'dismissed_at'              => null,
        'last_notified_by_email_at' => $firstSentAt,
    ]);

    // New notification arrives 30 minutes later (within cooldown)
    Carbon::setTestNow(Carbon::parse('2026-04-16 10:30:00', 'Europe/Amsterdam'));

    PatientNotifyEvent::dispatch(
        $person->id,
        'nieuw-document.pdf',
        NotificationReferenceType::FILE,
        99,
        true,
    );

    $person->refresh();

    // Should be scheduled at last_sent + interval = 10:00 + 120 = 12:00
    expect($person->patient_portal_notify_scheduled_at?->format('Y-m-d H:i'))->toBe('2026-04-16 12:00');

    // Time passes to after scheduled time
    Carbon::setTestNow(Carbon::parse('2026-04-16 12:01:00', 'Europe/Amsterdam'));

    $this->artisan('patient:send-notification-email')->assertExitCode(0);

    Mail::assertQueued(EmailMailable::class, 1);

    $person->refresh();
    expect($person->patient_portal_notify_scheduled_at)->toBeNull()
        ->and($person->patient_portal_last_notify_email_at->format('Y-m-d H:i'))->toBe('2026-04-16 12:01');

    // New notification should now be marked as notified
    $newNotificationNotified = PatientNotification::query()
        ->where('patient_id', $person->id)
        ->whereNull('dismissed_at')
        ->whereNotNull('last_notified_by_email_at')
        ->where('last_notified_by_email_at', '>=', Carbon::parse('2026-04-16 12:00:00', 'Europe/Amsterdam'))
        ->exists();

    expect($newNotificationNotified)->toBeTrue();
});

test('new event merges into already-notified notification and resets last_notified_by_email_at', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-16 10:00:00', 'Europe/Amsterdam'));

    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    $existing = PatientNotification::factory()->create([
        'patient_id'                => $person->id,
        'reference_type'            => NotificationReferenceType::FILE,
        'entity_names'              => ['eerste.pdf'],
        'dismissed_at'              => null,
        'last_notified_by_email_at' => Carbon::parse('2026-04-16 09:00:00', 'Europe/Amsterdam'),
    ]);

    PatientNotifyEvent::dispatch(
        $person->id,
        'tweede.pdf',
        NotificationReferenceType::FILE,
        99,
        true,
    );

    expect(PatientNotification::where('patient_id', $person->id)->count())->toBe(1);

    $existing->refresh();
    expect($existing->last_notified_by_email_at)->toBeNull()
        ->and($existing->entity_names)->toContain('tweede.pdf');
});

test('send command fails when new content email template is missing', function () {
    Mail::fake();

    EmailTemplate::query()->where('code', EmailTemplateCode::PATIENT_PORTAL_NOTIFICATION_NEW_CONTENT->value)->delete();

    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    PatientNotification::factory()->create([
        'patient_id'                => $person->id,
        'dismissed_at'              => null,
        'last_notified_by_email_at' => null,
    ]);

    $person->forceFill([
        'patient_portal_notify_scheduled_at' => now()->subMinute(),
    ])->save();

    $this->artisan('patient:send-notification-email')->assertExitCode(1);
});
