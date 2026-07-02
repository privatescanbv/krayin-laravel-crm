<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Lead\Models\Lead;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(makeUser(), 'user');
    Folder::create(['name' => 'inbox']);
    Folder::create(['name' => 'verwerkt']);
});

/**
 * Regression test: linking/unlinking an entity on an email must never rewrite
 * `from`. EmailController::update() previously injected the resolved mailbox
 * address into every update() call that omitted `from` (including pure
 * link/unlink requests), silently overwriting the real sender with the CRM's
 * own mailbox address and — because EmailRepository::update() didn't
 * normalize it — collapsing it into a bare string that rendered as an empty
 * "Van:" in the mail view.
 */
test('linking a person to an email does not overwrite the from field', function () {
    $person = Person::factory()->create();
    $folder = Folder::where('name', 'inbox')->first();

    $email = Email::create([
        'subject'   => 'Test',
        'reply'     => '<p>Body</p>',
        'from'      => ['name' => 'Original Sender', 'email' => 'sender@example.com'],
        'folder_id' => $folder->id,
        'source'    => 'email',
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'person_id' => $person->id,
    ]);

    expect($email->refresh()->from)->toBe([
        'name'  => 'Original Sender',
        'email' => 'sender@example.com',
    ]);
});

test('unlinking a lead from an email does not overwrite the from field', function () {
    $lead = Lead::factory()->create();
    $folder = Folder::where('name', 'inbox')->first();

    $email = Email::create([
        'subject'   => 'Test',
        'reply'     => '<p>Body</p>',
        'from'      => ['name' => 'Original Sender', 'email' => 'sender@example.com'],
        'folder_id' => $folder->id,
        'lead_id'   => $lead->id,
        'source'    => 'email',
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'lead_id' => null,
    ]);

    expect($email->refresh()->from)->toBe([
        'name'  => 'Original Sender',
        'email' => 'sender@example.com',
    ]);
});

test('from field is normalized to the standard structure even if a bare string is written via update', function () {
    $folder = Folder::where('name', 'inbox')->first();

    $email = Email::create([
        'subject'   => 'Test',
        'reply'     => '<p>Body</p>',
        'from'      => ['name' => 'Original Sender', 'email' => 'sender@example.com'],
        'folder_id' => $folder->id,
        'source'    => 'email',
    ]);

    // Simulate a draft-save/send update that legitimately sets `from` to a bare address.
    $this->put(route('admin.mail.update', $email->id), [
        'is_draft' => 1,
        'from'     => 'crm@privatescan.nl',
        'reply_to' => ['someone@example.com'],
        'reply'    => 'Updated body',
        'subject'  => 'Test',
    ]);

    expect($email->refresh()->from)->toBe([
        'name'  => config('mail.from.name', 'PrivateScan'),
        'email' => 'crm@privatescan.nl',
    ]);
});
