<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');
    $this->folder = Folder::create(['name' => 'inbox']);
});

function makeEmail(array $attributes = []): Email
{
    static $sequence = 1;

    $defaults = [
        'subject'       => 'Thread subject '.$sequence,
        'source'        => 'email',
        'user_type'     => 'person',
        'name'          => 'Thread User',
        'reply'         => 'Body '.$sequence,
        'is_read'       => 0,
        'folder_id'     => Folder::firstWhere('name', 'inbox')?->id,
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

test('mail view renders full descendant thread when opening a nested reply', function () {
    $root = makeEmail([
        'subject'       => 'Thread root subject',
        'reply'         => 'Root body',
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
    ]);

    $child = makeEmail([
        'subject'       => 'Re: Thread root subject',
        'reply'         => 'Direct child body',
        'parent_id'     => $root->id,
        'reply_to'      => ['customer@example.com'],
        'message_id'    => 'crm-child@example.com',
        'reference_ids' => ['<root@example.com>', 'crm-child@example.com'],
    ]);

    $nested = makeEmail([
        'subject'       => 'Re: Thread root subject',
        'reply'         => 'Nested child body',
        'parent_id'     => $child->id,
        'message_id'    => '<nested@example.com>',
        'reference_ids' => ['<root@example.com>', 'crm-child@example.com', '<nested@example.com>'],
    ]);

    $response = $this->get("/admin/mail/inbox/{$nested->id}");

    $response->assertOk();
    $response->assertSee('Root body', false);
    $response->assertSee('Direct child body', false);
    $response->assertSee('Nested child body', false);
    $response->assertSee(__('admin::app.mail.view.thread.subject').':', false);
    $response->assertSee('Thread root subject', false);
});

test('mail view reply form targets the thread root id', function () {
    $root = makeEmail([
        'subject'       => 'Thread root subject',
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
    ]);

    $child = makeEmail([
        'subject'       => 'Re: Thread root subject',
        'parent_id'     => $root->id,
        'reply_to'      => ['customer@example.com'],
        'message_id'    => 'crm-child@example.com',
        'reference_ids' => ['<root@example.com>', 'crm-child@example.com'],
    ]);

    $response = $this->get("/admin/mail/inbox/{$child->id}");

    $response->assertOk();
    $response->assertSee('name="parent_id"', false);
    $response->assertSee('value="'.$root->id.'"', false);
});

test('mail view marks the opened email as read', function () {
    $root = makeEmail([
        'subject'       => 'Thread root subject',
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
        'is_read'       => 0,
    ]);

    $child = makeEmail([
        'subject'       => 'Re: Thread root subject',
        'parent_id'     => $root->id,
        'reply_to'      => ['customer@example.com'],
        'message_id'    => 'crm-child@example.com',
        'reference_ids' => ['<root@example.com>', 'crm-child@example.com'],
        'is_read'       => 0,
    ]);

    $this->get("/admin/mail/inbox/{$child->id}")->assertOk();

    expect($child->fresh()->is_read)->toBe(1)
        ->and($root->fresh()->is_read)->toBe(0);
});
