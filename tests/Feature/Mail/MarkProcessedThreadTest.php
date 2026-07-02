<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

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
});

function makeThreadEmail(array $attributes = []): Email
{
    static $sequence = 1;

    $defaults = [
        'subject'       => 'Thread subject',
        'source'        => 'email',
        'user_type'     => 'person',
        'name'          => 'Customer',
        'reply'         => 'Body '.$sequence,
        'is_read'       => 0,
        'folder_id'     => Folder::firstWhere('name', EmailFolderEnum::INBOX->value)?->id,
        'mailbox_key'   => 'privatescan',
        'from'          => ['email' => 'customer@example.com', 'name' => 'Customer'],
        'reply_to'      => [],
        'cc'            => [],
        'bcc'           => [],
        'unique_id'     => 'unique-'.$sequence,
        'message_id'    => '<message-'.$sequence.'@example.com>',
        'reference_ids' => ['<message-'.$sequence.'@example.com>'],
        'created_at'    => now()->addMinutes($sequence),
        'updated_at'    => now()->addMinutes($sequence),
    ];

    $sequence++;

    return Email::create(array_merge($defaults, $attributes));
}

test('mark processed moves every inbox email in the thread to Verwerkt', function () {
    $root = makeThreadEmail([
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
    ]);

    $child = makeThreadEmail([
        'parent_id'     => $root->id,
        'message_id'    => '<child@example.com>',
        'reference_ids' => ['<root@example.com>', '<child@example.com>'],
    ]);

    $nested = makeThreadEmail([
        'parent_id'     => $child->id,
        'message_id'    => '<nested@example.com>',
        'reference_ids' => ['<root@example.com>', '<child@example.com>', '<nested@example.com>'],
    ]);

    $this->postJson(route('admin.mail.mark_processed', $nested->id))
        ->assertOk();

    expect($root->fresh()->folder_id)->toBe($this->verwerktFolder->id)
        ->and($child->fresh()->folder_id)->toBe($this->verwerktFolder->id)
        ->and($nested->fresh()->folder_id)->toBe($this->verwerktFolder->id);
});

test('mail view shows mark processed when opened email is still in inbox even if thread root is already Verwerkt', function () {
    $root = makeThreadEmail([
        'folder_id'     => Folder::firstWhere('name', EmailFolderEnum::PROCESSED->value)?->id,
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
    ]);

    $child = makeThreadEmail([
        'parent_id'     => $root->id,
        'folder_id'     => Folder::firstWhere('name', EmailFolderEnum::INBOX->value)?->id,
        'message_id'    => '<child@example.com>',
        'reference_ids' => ['<root@example.com>', '<child@example.com>'],
    ]);

    $this->get('/admin/mail/'.rawurlencode(EmailFolderEnum::INBOX->value).'/'.$child->id)
        ->assertOk()
        ->assertSee('openedEmailIsProcessed: false', false);
});

test('mail view hides mark processed when opened email is already Verwerkt', function () {
    $root = makeThreadEmail([
        'folder_id'     => Folder::firstWhere('name', EmailFolderEnum::PROCESSED->value)?->id,
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
    ]);

    $this->get('/admin/mail/'.rawurlencode(EmailFolderEnum::PROCESSED->value).'/'.$root->id)
        ->assertOk()
        ->assertSee('openedEmailIsProcessed: true', false);
});
