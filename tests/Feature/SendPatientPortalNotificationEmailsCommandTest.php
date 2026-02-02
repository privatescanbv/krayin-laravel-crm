<?php

use App\Models\PatientNotification;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Webkul\Contact\Models\Person;
use Webkul\Email\Mails\Email as EmailMailable;
use Webkul\Email\Models\Email;
use Webkul\EmailTemplate\Models\EmailTemplate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    config(['mail.send_only_accept' => '*@example.com']);
});

test('it sends one notification email per patient and updates last_notified_by_email_at', function () {
    Mail::fake();

    EmailTemplate::factory()->create([
        'name'    => 'patient-portal-notification',
        'code'    => 'patient-portal-notification',
        'subject' => 'Nieuwe melding in uw patiëntportaal',
        'content' => '<p>Geachte heer/mevrouw {{ $lastname }},</p><p>Ga naar {%portal_url%}</p>',
    ]);

    $person = Person::factory()->create([
        'last_name' => 'Jansen',
        'emails'    => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    PatientNotification::factory()->count(2)->create([
        'patient_id'                => $person->id,
        'dismissed_at'              => null,
        'last_notified_by_email_at' => null,
    ]);

    $this->artisan('patient:send-notification-email')->assertExitCode(0);

    expect(Email::where('person_id', $person->id)->count())->toBe(1);

    $updatedCount = PatientNotification::query()
        ->where('patient_id', $person->id)
        ->whereNotNull('last_notified_by_email_at')
        ->count();

    expect($updatedCount)->toBe(2);

    Mail::assertQueued(EmailMailable::class, 1);
});
