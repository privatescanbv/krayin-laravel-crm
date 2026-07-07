<?php

use App\Console\Commands\RepairMislinkedEmailThreads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;

uses(RefreshDatabase::class);

beforeEach(function () {
    Folder::create(['name' => 'inbox']);
    config(['mail.mailboxes' => [
        'privatescan' => [
            'address' => 'crm@privatescan.nl',
        ],
    ]]);
});

function makeMislinkedEmail(array $attributes = []): Email
{
    static $sequence = 1;

    $defaults = [
        'subject'       => 'Notificatie '.$sequence,
        'source'        => 'system',
        'user_type'     => 'user',
        'name'          => 'CRM',
        'reply'         => 'Body '.$sequence,
        'is_read'       => 0,
        'folder_id'     => Folder::firstWhere('name', 'inbox')?->id,
        'mailbox_key'   => 'privatescan',
        'from'          => ['email' => 'crm@privatescan.nl', 'name' => 'PrivateScan CRM'],
        'reply_to'      => [],
        'cc'            => [],
        'bcc'           => [],
        'unique_id'     => 'mislinked-unique-'.$sequence,
        'message_id'    => '<mislinked-'.$sequence.'@privatescan.nl>',
        'reference_ids' => ['<mislinked-'.$sequence.'@privatescan.nl>'],
        'created_at'    => now()->addMinutes($sequence),
        'updated_at'    => now()->addMinutes($sequence),
    ];

    $sequence++;

    return Email::create(array_merge($defaults, $attributes));
}

test('detaches an email that shares no id-based link with its thread', function () {
    $root = makeMislinkedEmail([
        'message_id'    => '<root@privatescan.nl>',
        'reference_ids' => ['<root@privatescan.nl>'],
        'reply_to'      => ['kees@sdsbv.nl'],
    ]);

    // Wrongly attached: only participant/subject matched, no shared message-id or reference.
    $mislinked = makeMislinkedEmail([
        'parent_id'     => $root->id,
        'message_id'    => '<other@privatescan.nl>',
        'reference_ids' => ['<other@privatescan.nl>'],
        'reply_to'      => ['erwinslootbeek@hotmail.com'],
    ]);

    $this->artisan(RepairMislinkedEmailThreads::class)
        ->expectsOutputToContain((string) $mislinked->id)
        ->assertSuccessful();

    expect($mislinked->fresh()->parent_id)->toBeNull();
    expect($root->fresh()->parent_id)->toBeNull();
});

test('keeps a genuine reply that references its parent message id', function () {
    $root = makeMislinkedEmail([
        'message_id'    => '<real-root@privatescan.nl>',
        'reference_ids' => ['<real-root@privatescan.nl>'],
    ]);

    $reply = makeMislinkedEmail([
        'parent_id'     => $root->id,
        'message_id'    => '<real-reply@privatescan.nl>',
        'reference_ids' => ['<real-root@privatescan.nl>', '<real-reply@privatescan.nl>'],
    ]);

    $this->artisan(RepairMislinkedEmailThreads::class)->assertSuccessful();

    expect($reply->fresh()->parent_id)->toBe($root->id);
});

test('dry run reports without persisting changes', function () {
    $root = makeMislinkedEmail();

    $mislinked = makeMislinkedEmail([
        'parent_id'     => $root->id,
        'message_id'    => '<no-link@privatescan.nl>',
        'reference_ids' => ['<no-link@privatescan.nl>'],
    ]);

    $this->artisan(RepairMislinkedEmailThreads::class, ['--dry-run' => true])
        ->expectsOutputToContain((string) $mislinked->id)
        ->assertSuccessful();

    expect($mislinked->fresh()->parent_id)->toBe($root->id);
});

test('re-links a detached inbound email to the sender person', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'label' => 'work']],
    ]);

    $root = makeMislinkedEmail([
        'message_id'    => '<root2@privatescan.nl>',
        'reference_ids' => ['<root2@privatescan.nl>'],
    ]);

    $mislinked = makeMislinkedEmail([
        'parent_id'     => $root->id,
        'from'          => ['email' => 'patient@example.com', 'name' => 'Patient'],
        'message_id'    => '<inbound@example.com>',
        'reference_ids' => ['<inbound@example.com>'],
        'person_id'     => null,
    ]);

    $this->artisan(RepairMislinkedEmailThreads::class)->assertSuccessful();

    $fresh = $mislinked->fresh();

    expect($fresh->parent_id)->toBeNull();
    expect($fresh->person_id)->toBe($person->id);
});

test('re-links an outbound notification via its recipient when sender is the crm mailbox', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'erwin@example.com', 'label' => 'home']],
    ]);

    $root = makeMislinkedEmail([
        'message_id'    => '<root3@privatescan.nl>',
        'reference_ids' => ['<root3@privatescan.nl>'],
        'reply_to'      => ['someone-else@example.com'],
    ]);

    $mislinked = makeMislinkedEmail([
        'parent_id'     => $root->id,
        'from'          => ['email' => 'crm@privatescan.nl', 'name' => 'PrivateScan CRM'],
        'reply_to'      => ['erwin@example.com'],
        'message_id'    => '<outbound@privatescan.nl>',
        'reference_ids' => ['<outbound@privatescan.nl>'],
        'person_id'     => null,
    ]);

    $this->artisan(RepairMislinkedEmailThreads::class)->assertSuccessful();

    $fresh = $mislinked->fresh();

    expect($fresh->parent_id)->toBeNull();
    expect($fresh->person_id)->toBe($person->id);
});

test('id option expands to the whole thread so a sibling gets repaired', function () {
    $root = makeMislinkedEmail([
        'message_id'    => '<thread-root@privatescan.nl>',
        'reference_ids' => ['<thread-root@privatescan.nl>'],
        'reply_to'      => ['kees@sdsbv.nl'],
    ]);

    // Genuine reply: references the root's message id, must be kept.
    $genuineReply = makeMislinkedEmail([
        'parent_id'     => $root->id,
        'from'          => ['email' => 'kees@sdsbv.nl', 'name' => 'Kees'],
        'message_id'    => '<genuine-reply@sdsbv.nl>',
        'reference_ids' => ['<genuine-reply@sdsbv.nl>', '<thread-root@privatescan.nl>'],
    ]);

    // Mislinked sibling: shares no identifier with the thread.
    $mislinkedSibling = makeMislinkedEmail([
        'parent_id'     => $root->id,
        'from'          => ['email' => 'crm@privatescan.nl', 'name' => 'PrivateScan CRM'],
        'reply_to'      => ['erwinslootbeek@hotmail.com'],
        'message_id'    => '<sibling@privatescan.nl>',
        'reference_ids' => ['<sibling@privatescan.nl>'],
    ]);

    // Pass the id of the genuine reply; the offending sibling must still be repaired.
    $this->artisan(RepairMislinkedEmailThreads::class, ['--id' => [$genuineReply->id]])
        ->expectsOutputToContain((string) $mislinkedSibling->id)
        ->assertSuccessful();

    expect($mislinkedSibling->fresh()->parent_id)->toBeNull();
    expect($genuineReply->fresh()->parent_id)->toBe($root->id);
});

test('only repairs emails within the requested month window', function () {
    $root = makeMislinkedEmail([
        'created_at'    => now()->subMonth(),
        'message_id'    => '<recent-root@privatescan.nl>',
        'reference_ids' => ['<recent-root@privatescan.nl>'],
    ]);

    $recentMislinked = makeMislinkedEmail([
        'parent_id'     => $root->id,
        'created_at'    => now()->subMonth(),
        'message_id'    => '<recent-mislinked@privatescan.nl>',
        'reference_ids' => ['<recent-mislinked@privatescan.nl>'],
    ]);

    $oldRoot = makeMislinkedEmail([
        'created_at'    => now()->subMonths(4),
        'message_id'    => '<old-root@privatescan.nl>',
        'reference_ids' => ['<old-root@privatescan.nl>'],
    ]);

    $oldMislinked = makeMislinkedEmail([
        'parent_id'     => $oldRoot->id,
        'created_at'    => now()->subMonths(4),
        'message_id'    => '<old-mislinked@privatescan.nl>',
        'reference_ids' => ['<old-mislinked@privatescan.nl>'],
    ]);

    $this->artisan(RepairMislinkedEmailThreads::class, ['--months' => 2])
        ->expectsOutputToContain((string) $recentMislinked->id)
        ->doesntExpectOutputToContain((string) $oldMislinked->id)
        ->assertSuccessful();

    expect($recentMislinked->fresh()->parent_id)->toBeNull();
    expect($oldMislinked->fresh()->parent_id)->toBe($oldRoot->id);
});
