<?php

use App\Models\PatientNotification;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Webkul\Contact\Models\Person;
use Webkul\Email\Mails\Email as EmailMailable;
use Webkul\Email\Models\Email;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    config(['mail.send_only_accept' => '*@example.com']);
});

test('it sends one notification email per patient and updates last_notified_by_email_at', function () {
    Mail::fake();

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
        'patient_portal_notify_scheduled_at' => Carbon::now()->subMinute(),
    ])->save();

    $this->artisan('patient:send-notification-email')->assertExitCode(0);

    expect(Email::where('person_id', $person->id)->count())->toBe(1);

    $updatedCount = PatientNotification::query()
        ->where('patient_id', $person->id)
        ->whereNotNull('last_notified_by_email_at')
        ->count();

    expect($updatedCount)->toBe(2);

    Mail::assertQueued(EmailMailable::class, 1);
});
