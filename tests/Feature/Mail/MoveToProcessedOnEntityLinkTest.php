<?php

use App\Models\Clinic;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

/**
 * Tests for the "move to Verwerkt on entity link" feature.
 *
 * Use case: when an email is linked to an entity (lead, person, etc.) via the update
 * endpoint, it should automatically be moved out of any inbox-type folder to "Verwerkt".
 *
 * Inbox-type folders: Inbox, Privatescan webforms, Hernia Poli webforms, Klinieken,
 * Nieuwsbrief reacties.
 * Non-inbox folders (Sent, Verwerkt, Draft, Trash, Geen opvolging) are left untouched.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');

    $this->inboxFolder = Folder::create([
        'name'         => EmailFolderEnum::INBOX->value,
        'parent_id'    => null,
        'order'        => 1,
        'is_deletable' => false,
    ]);

    $this->verwerktFolder = Folder::create([
        'name'         => EmailFolderEnum::PROCESSED->value,
        'parent_id'    => null,
        'order'        => 3,
        'is_deletable' => false,
    ]);

    $this->sentFolder = Folder::create([
        'name'         => EmailFolderEnum::SENT->value,
        'parent_id'    => null,
        'order'        => 5,
        'is_deletable' => false,
    ]);

    $this->lead = Lead::factory()->create();
});

// ---------------------------------------------------------------------------
// Inbox → Verwerkt for each entity field
// ---------------------------------------------------------------------------

test('email in Inbox is moved to Verwerkt when linked to a lead', function () {
    $email = Email::create([
        'subject'   => 'Test',
        'from'      => ['test@example.com'],
        'reply'     => 'Body',
        'folder_id' => $this->inboxFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'lead_id' => $this->lead->id,
    ]);

    expect($email->refresh()->folder_id)->toBe($this->verwerktFolder->id);
});

test('email in Inbox is moved to Verwerkt when linked to a person', function () {
    $person = Person::factory()->create();

    $email = Email::create([
        'subject'   => 'Test',
        'from'      => ['test@example.com'],
        'reply'     => 'Body',
        'folder_id' => $this->inboxFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'person_id' => $person->id,
    ]);

    expect($email->refresh()->folder_id)->toBe($this->verwerktFolder->id);
});

test('email in Inbox is moved to Verwerkt when linked to a sales lead', function () {
    $salesLead = SalesLead::factory()->create();

    $email = Email::create([
        'subject'   => 'Test',
        'from'      => ['test@example.com'],
        'reply'     => 'Body',
        'folder_id' => $this->inboxFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'sales_lead_id' => $salesLead->id,
    ]);

    expect($email->refresh()->folder_id)->toBe($this->verwerktFolder->id);
});

test('email in Inbox is moved to Verwerkt when linked to a clinic', function () {
    $clinic = Clinic::factory()->create();

    $email = Email::create([
        'subject'   => 'Test',
        'from'      => ['test@example.com'],
        'reply'     => 'Body',
        'folder_id' => $this->inboxFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'clinic_id' => $clinic->id,
    ]);

    expect($email->refresh()->folder_id)->toBe($this->verwerktFolder->id);
});

test('email in Inbox is moved to Verwerkt when linked to an order', function () {
    $order = Order::factory()->create();

    $email = Email::create([
        'subject'   => 'Test',
        'from'      => ['test@example.com'],
        'reply'     => 'Body',
        'folder_id' => $this->inboxFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'order_id' => $order->id,
    ]);

    expect($email->refresh()->folder_id)->toBe($this->verwerktFolder->id);
});

// ---------------------------------------------------------------------------
// Inbox sub-folders → Verwerkt
// ---------------------------------------------------------------------------

test('email in Privatescan webforms sub-folder is moved to Verwerkt on entity link', function () {
    $subFolder = Folder::create([
        'name'      => EmailFolderEnum::PRIVATESCAN_WEBFORM->value,
        'parent_id' => $this->inboxFolder->id,
        'order'     => 1,
    ]);

    $email = Email::create([
        'subject'   => 'Webform submission',
        'from'      => ['patient@example.com'],
        'reply'     => 'Body',
        'folder_id' => $subFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'lead_id' => $this->lead->id,
    ]);

    expect($email->refresh()->folder_id)->toBe($this->verwerktFolder->id);
});

test('email in Hernia Poli webforms sub-folder is moved to Verwerkt on entity link', function () {
    $subFolder = Folder::create([
        'name'      => EmailFolderEnum::HERNIA_WEBFORM->value,
        'parent_id' => $this->inboxFolder->id,
        'order'     => 2,
    ]);

    $email = Email::create([
        'subject'   => 'Hernia form',
        'from'      => ['patient@example.com'],
        'reply'     => 'Body',
        'folder_id' => $subFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'lead_id' => $this->lead->id,
    ]);

    expect($email->refresh()->folder_id)->toBe($this->verwerktFolder->id);
});

test('email in Klinieken sub-folder is moved to Verwerkt on entity link', function () {
    $subFolder = Folder::create([
        'name'      => EmailFolderEnum::CLINICS->value,
        'parent_id' => $this->inboxFolder->id,
        'order'     => 3,
    ]);

    $email = Email::create([
        'subject'   => 'Kliniek mail',
        'from'      => ['clinic@example.com'],
        'reply'     => 'Body',
        'folder_id' => $subFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'lead_id' => $this->lead->id,
    ]);

    expect($email->refresh()->folder_id)->toBe($this->verwerktFolder->id);
});

test('email in Nieuwsbrief reacties sub-folder is moved to Verwerkt on entity link', function () {
    $subFolder = Folder::create([
        'name'      => EmailFolderEnum::NEWSLETTER->value,
        'parent_id' => $this->inboxFolder->id,
        'order'     => 4,
    ]);

    $email = Email::create([
        'subject'   => 'Newsletter reply',
        'from'      => ['subscriber@example.com'],
        'reply'     => 'Body',
        'folder_id' => $subFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'lead_id' => $this->lead->id,
    ]);

    expect($email->refresh()->folder_id)->toBe($this->verwerktFolder->id);
});

// ---------------------------------------------------------------------------
// Non-inbox folders are NOT touched
// ---------------------------------------------------------------------------

test('email in Sent folder is NOT moved when linked to an entity', function () {
    $email = Email::create([
        'subject'   => 'Sent mail',
        'from'      => ['agent@example.com'],
        'reply'     => 'Body',
        'folder_id' => $this->sentFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'lead_id' => $this->lead->id,
    ]);

    expect($email->refresh()->folder_id)->toBe($this->sentFolder->id);
});

test('email already in Verwerkt stays in Verwerkt when entity link is re-sent (idempotent)', function () {
    $email = Email::create([
        'subject'   => 'Already processed',
        'from'      => ['agent@example.com'],
        'reply'     => 'Body',
        'folder_id' => $this->verwerktFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'lead_id' => $this->lead->id,
    ]);

    expect($email->refresh()->folder_id)->toBe($this->verwerktFolder->id);
});

// ---------------------------------------------------------------------------
// No entity link → folder unchanged
// ---------------------------------------------------------------------------

test('email in Inbox is NOT moved when update contains no entity link', function () {
    $email = Email::create([
        'subject'   => 'Unlinked mail',
        'from'      => ['test@example.com'],
        'reply'     => 'Body',
        'folder_id' => $this->inboxFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'subject' => 'Updated subject',
    ]);

    expect($email->refresh()->folder_id)->toBe($this->inboxFolder->id);
});

test('email in Inbox is NOT moved when entity fields are present but empty', function () {
    $email = Email::create([
        'subject'   => 'Unlinked mail',
        'from'      => ['test@example.com'],
        'reply'     => 'Body',
        'folder_id' => $this->inboxFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'lead_id'       => '',
        'person_id'     => null,
        'sales_lead_id' => 0,
    ]);

    expect($email->refresh()->folder_id)->toBe($this->inboxFolder->id);
});

// ---------------------------------------------------------------------------
// Graceful degradation when Verwerkt folder is missing
// ---------------------------------------------------------------------------

test('email stays in Inbox when Verwerkt folder does not exist', function () {
    $this->verwerktFolder->delete();

    $email = Email::create([
        'subject'   => 'Test',
        'from'      => ['test@example.com'],
        'reply'     => 'Body',
        'folder_id' => $this->inboxFolder->id,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'lead_id' => $this->lead->id,
    ]);

    // Should not throw; email stays in Inbox
    expect($email->refresh()->folder_id)->toBe($this->inboxFolder->id);
});
