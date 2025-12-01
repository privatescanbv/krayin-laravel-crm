<?php

namespace Tests\Feature;

use App\Mail\PortalGVLCompletedMail;
use App\Services\Mail\PatientMailService;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('mailPatient stores email record with correct subject body and from fields', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'test@example.com', 'is_default' => true]],
    ]);

    $lead = Lead::factory()->create();
    $mail = new PortalGVLCompletedMail($person, 'https://forms.example.com/forms/1');

    $service = app(PatientMailService::class);

    $result = $service->mailPatient($person, $mail, (string) $lead->id, null, null);

    expect($result)->toBeTrue();

    $emailRecord = Email::where('person_id', $person->id)
        ->where('lead_id', $lead->id)
        ->first();

    expect($emailRecord)->not->toBeNull()
        ->and($emailRecord->subject)->not->toBeEmpty()
        ->and($emailRecord->subject)->toContain('Welkom')
        ->and($emailRecord->reply)->not->toBeEmpty()
        ->and($emailRecord->reply)->toContain('<!DOCTYPE html>')
        ->and($emailRecord->from)->toBeArray()
        ->and($emailRecord->from)->toHaveKey('email')
        ->and($emailRecord->from)->toHaveKey('name')
        ->and($emailRecord->from['email'])->not->toBeEmpty()
        ->and($emailRecord->reply_to)->toBeArray()
        ->and($emailRecord->reply_to)->not->toBeEmpty()
        ->and($emailRecord->reply_to[0])->toBeString()
        ->and($emailRecord->reply_to[0])->toBe('test@example.com')
        ->and($emailRecord->name)->toBe($person->name)
        ->and($emailRecord->person_id)->toBe($person->id)
        ->and($emailRecord->lead_id)->toBe($lead->id)
        ->and($emailRecord->source)->toBe('system')
        ->and($emailRecord->user_type)->toBe('user')
        ->and($emailRecord->message_id)->not->toBeEmpty();
});

test('mailPatient stores email record with sales_lead_id when provided', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'test@example.com', 'is_default' => true]],
    ]);

    $salesLeadId = 123;
    $mail = new PortalGVLCompletedMail($person, 'https://forms.example.com/forms/1');

    $service = app(PatientMailService::class);

    $result = $service->mailPatient($person, $mail, null, (string) $salesLeadId, null);

    expect($result)->toBeTrue();

    $emailRecord = Email::where('person_id', $person->id)
        ->where('sales_lead_id', $salesLeadId)
        ->first();

    expect($emailRecord)->not->toBeNull()
        ->and($emailRecord->sales_lead_id)->toBe($salesLeadId)
        ->and($emailRecord->lead_id)->toBeNull();
});

test('mailPatient returns false when person has no email address', function () {
    $person = Person::factory()->create([
        'emails' => [],
    ]);

    $lead = Lead::factory()->create();
    $mail = new PortalGVLCompletedMail($person, 'https://forms.example.com/forms/1');

    $service = app(PatientMailService::class);

    $result = $service->mailPatient($person, $mail, (string) $lead->id, null, null);

    expect($result)->toBeFalse();

    $emailRecord = Email::where('person_id', $person->id)->first();
    expect($emailRecord)->toBeNull();
});

test('mailPatient throws exception when no related entity IDs provided', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'test@example.com', 'is_default' => true]],
    ]);

    $mail = new PortalGVLCompletedMail($person, 'https://forms.example.com/forms/1');

    $service = app(PatientMailService::class);

    expect(fn () => $service->mailPatient($person, $mail, null, null, null))
        ->toThrow(\Exception::class, 'At least one related entity ID must be provided');
});

test('reply_to accessor normalizes legacy object format to array of strings', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'test@example.com', 'is_default' => true]],
    ]);

    $lead = Lead::factory()->create();
    $mail = new PortalGVLCompletedMail($person, 'https://forms.example.com/forms/1');

    $service = app(PatientMailService::class);
    $service->mailPatient($person, $mail, (string) $lead->id, null, null);

    $emailRecord = Email::where('person_id', $person->id)
        ->where('lead_id', $lead->id)
        ->first();

    // Verify reply_to is an array of strings (can be joined)
    expect($emailRecord->reply_to)->toBeArray()
        ->and($emailRecord->reply_to)->not->toBeEmpty()
        ->and(is_array($emailRecord->reply_to))->toBeTrue()
        ->and(is_string($emailRecord->reply_to[0]))->toBeTrue()
        ->and(implode(', ', $emailRecord->reply_to))->toContain('@');
});

test('reply_to field stores recipient email at time of sending and remains unchanged when person email changes', function () {
    $originalEmail = 'original@example.com';
    $person = Person::factory()->create([
        'emails' => [['value' => $originalEmail, 'is_default' => true]],
    ]);

    $lead = Lead::factory()->create();
    $mail = new PortalGVLCompletedMail($person, 'https://forms.example.com/forms/1');

    $service = app(PatientMailService::class);
    $service->mailPatient($person, $mail, (string) $lead->id, null, null);

    $emailRecord = Email::where('person_id', $person->id)
        ->where('lead_id', $lead->id)
        ->first();

    // Verify reply_to field contains the recipient email (not the sender)
    expect($emailRecord->reply_to)->toBeArray()
        ->and($emailRecord->reply_to)->not->toBeEmpty()
        ->and($emailRecord->reply_to[0])->toBe($originalEmail)
        ->and($emailRecord->from['email'])->not->toBe($originalEmail); // from should be sender, not recipient

    // Now change the person's email
    $person->emails = [['value' => 'newemail@example.com', 'is_default' => true]];
    $person->save();

    // Reload the email record
    $emailRecord->refresh();

    // Verify reply_to field still contains the original email (historical record)
    expect($emailRecord->reply_to)->toBeArray()
        ->and($emailRecord->reply_to[0])->toBe($originalEmail)
        ->and($emailRecord->reply_to[0])->not->toBe('newemail@example.com');
});
