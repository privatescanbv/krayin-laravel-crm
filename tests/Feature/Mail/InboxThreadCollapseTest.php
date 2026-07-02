<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Admin\DataGrids\Mail\EmailDataGrid;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Email\Repositories\EmailRepository;
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

function makeInboxThreadEmail(array $attributes = []): Email
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

test('inbox datagrid shows only the latest email per thread root', function () {
    $root = makeInboxThreadEmail([
        'folder_id'     => test()->verwerktFolder->id,
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
    ]);

    $olderInbox = makeInboxThreadEmail([
        'parent_id'     => $root->id,
        'message_id'    => '<older@example.com>',
        'reference_ids' => ['<root@example.com>', '<older@example.com>'],
    ]);

    $latestInbox = makeInboxThreadEmail([
        'parent_id'     => $olderInbox->id,
        'message_id'    => '<latest@example.com>',
        'reference_ids' => ['<root@example.com>', '<older@example.com>', '<latest@example.com>'],
    ]);

    request()->merge(['route' => EmailFolderEnum::INBOX->value]);

    $ids = (new EmailDataGrid)
        ->prepareQueryBuilder()
        ->pluck('emails.id')
        ->all();

    expect($ids)->toContain($latestInbox->id)
        ->and($ids)->not->toContain($olderInbox->id);
});

test('archiveOlderInboxThreadEmailsExcept moves earlier inbox siblings to Verwerkt', function () {
    $root = makeInboxThreadEmail([
        'folder_id'     => test()->verwerktFolder->id,
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
    ]);

    $olderInbox = makeInboxThreadEmail([
        'parent_id'     => $root->id,
        'message_id'    => '<older@example.com>',
        'reference_ids' => ['<root@example.com>', '<older@example.com>'],
    ]);

    $latestInbox = makeInboxThreadEmail([
        'parent_id'     => $olderInbox->id,
        'message_id'    => '<latest@example.com>',
        'reference_ids' => ['<root@example.com>', '<older@example.com>', '<latest@example.com>'],
    ]);

    app(EmailRepository::class)->archiveOlderInboxThreadEmailsExcept($latestInbox);

    expect($olderInbox->fresh()->folder_id)->toBe(test()->verwerktFolder->id)
        ->and($latestInbox->fresh()->folder_id)->toBe(test()->inboxFolder->id)
        ->and($root->fresh()->folder_id)->toBe(test()->verwerktFolder->id);
});

test('inbox datagrid still shows unrelated threads separately', function () {
    $threadA = makeInboxThreadEmail(['subject' => 'Thread A']);
    $threadB = makeInboxThreadEmail(['subject' => 'Thread B']);

    request()->merge(['route' => EmailFolderEnum::INBOX->value]);

    $ids = (new EmailDataGrid)
        ->prepareQueryBuilder()
        ->pluck('emails.id')
        ->all();

    expect($ids)->toContain($threadA->id, $threadB->id);
});
