<?php

use App\Console\Commands\RepairEmailThreads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->folder = Folder::create(['name' => 'inbox']);
    config(['mail.mailboxes' => [
        'privatescan' => [
            'address' => 'crm@privatescan.nl',
        ],
    ]]);
});

function makeRepairEmail(array $attributes = []): Email
{
    static $sequence = 1;

    $defaults = [
        'subject'       => 'Repair thread '.$sequence,
        'source'        => 'email',
        'user_type'     => 'person',
        'name'          => 'Repair User',
        'reply'         => 'Repair body '.$sequence,
        'is_read'       => 0,
        'folder_id'     => Folder::firstWhere('name', 'inbox')?->id,
        'mailbox_key'   => 'privatescan',
        'from'          => ['email' => 'customer@example.com', 'name' => 'Customer'],
        'reply_to'      => [],
        'cc'            => [],
        'bcc'           => [],
        'unique_id'     => 'repair-unique-'.$sequence,
        'message_id'    => '<repair-message-'.$sequence.'@example.com>',
        'reference_ids' => ['<repair-message-'.$sequence.'@example.com>'],
        'created_at'    => now()->addMinutes($sequence),
        'updated_at'    => now()->addMinutes($sequence),
    ];

    $sequence++;

    return Email::create(array_merge($defaults, $attributes));
}

test('repair email threads command supports dry run without persisting changes', function () {
    $root = makeRepairEmail([
        'subject'       => 'aanvraag mri 2-6',
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
    ]);

    makeRepairEmail([
        'subject'       => 'Re: aanvraag mri 2-6',
        'parent_id'     => $root->id,
        'from'          => ['email' => 'privatescan@example.com', 'name' => 'Privatescan'],
        'reply_to'      => ['customer@example.com'],
        'message_id'    => 'crm-reply@example.com',
        'reference_ids' => ['<root@example.com>', 'crm-reply@example.com'],
    ]);

    $orphan = makeRepairEmail([
        'subject'       => 'Re: aanvraag mri 2-6',
        'message_id'    => '<orphan@example.com>',
        'reference_ids' => ['<orphan@example.com>'],
    ]);

    $this->artisan(RepairEmailThreads::class, ['--dry-run' => true, '--id' => [$orphan->id]])
        ->expectsOutputToContain((string) $orphan->id)
        ->assertSuccessful();

    expect($orphan->fresh()->parent_id)->toBeNull()
        ->and($orphan->fresh()->reply_to)->toBe([]);
});

test('repair email threads command links orphan replies back to the thread root', function () {
    $root = makeRepairEmail([
        'subject'       => 'aanvraag mri 2-6',
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
    ]);

    $crmReply = makeRepairEmail([
        'subject'       => 'Re: aanvraag mri 2-6',
        'parent_id'     => $root->id,
        'from'          => ['email' => 'privatescan@example.com', 'name' => 'Privatescan'],
        'reply_to'      => ['customer@example.com'],
        'message_id'    => 'crm-reply@example.com',
        'reference_ids' => ['<root@example.com>', 'crm-reply@example.com'],
    ]);

    $orphan = makeRepairEmail([
        'subject'       => 'Re: aanvraag mri 2-6',
        'message_id'    => '<orphan@example.com>',
        'reference_ids' => ['<orphan@example.com>'],
    ]);

    $referencedOrphan = makeRepairEmail([
        'subject'       => 'Re: aanvraag mri 2-6',
        'message_id'    => '<referenced-orphan@example.com>',
        'reference_ids' => ['<root@example.com>', 'crm-reply@example.com', '<referenced-orphan@example.com>'],
    ]);

    $this->artisan(RepairEmailThreads::class, ['--id' => [$orphan->id, $referencedOrphan->id]])
        ->expectsOutputToContain((string) $orphan->id)
        ->expectsOutputToContain((string) $referencedOrphan->id)
        ->assertSuccessful();

    expect($orphan->fresh()->parent_id)->toBe($root->id)
        ->and($referencedOrphan->fresh()->parent_id)->toBe($root->id);
});

test('repair email threads command backfills empty reply_to from mailbox address', function () {
    $email = makeRepairEmail([
        'subject'    => 'Inbound without recipients',
        'reply_to'   => [],
        'mailbox_key'=> 'privatescan',
    ]);

    $this->artisan(RepairEmailThreads::class, ['--id' => [$email->id]])
        ->assertSuccessful();

    expect($email->fresh()->reply_to)->toBe(['crm@privatescan.nl']);
});
