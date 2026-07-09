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
});

function makeDetachThreadEmail(array $attributes = []): Email
{
    static $sequence = 1;

    $defaults = [
        'subject'       => 'Thread subject '.$sequence,
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

test('detach thread clears parent_id and returns redirect url', function () {
    $root = makeDetachThreadEmail([
        'subject'       => 'Behandelingsovereenkomst',
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
    ]);

    $mislinked = makeDetachThreadEmail([
        'subject'       => 'Medicatieoverzicht M.A. Bondrager',
        'parent_id'     => $root->id,
        'message_id'    => '<mislinked@example.com>',
        'reference_ids' => ['<mislinked@example.com>'],
    ]);

    $response = $this->postJson(route('admin.mail.detach_thread', $mislinked->id));

    $response->assertOk()
        ->assertJsonPath('redirect_url', route('admin.mail.view', [
            'route' => EmailFolderEnum::INBOX->value,
            'id'    => $mislinked->id,
        ]));

    expect($mislinked->fresh()->parent_id)->toBeNull();
});

test('detach thread rejects emails that are not linked', function () {
    $root = makeDetachThreadEmail([
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
    ]);

    $this->postJson(route('admin.mail.detach_thread', $root->id))
        ->assertStatus(422)
        ->assertJsonPath('message', trans('admin::app.mail.view.thread.detach-not-linked'));

    expect($root->fresh()->parent_id)->toBeNull();
});

test('mail view shows subject and detach action on linked emails in thread', function () {
    $root = makeDetachThreadEmail([
        'subject'       => 'Behandelingsovereenkomst',
        'message_id'    => '<root@example.com>',
        'reference_ids' => ['<root@example.com>'],
    ]);

    $child = makeDetachThreadEmail([
        'subject'       => 'Re: Behandelingsovereenkomst',
        'parent_id'     => $root->id,
        'message_id'    => '<child@example.com>',
        'reference_ids' => ['<root@example.com>', '<child@example.com>'],
    ]);

    $mislinked = makeDetachThreadEmail([
        'subject'       => 'Medicatieoverzicht M.A. Bondrager',
        'parent_id'     => $child->id,
        'message_id'    => '<mislinked@example.com>',
        'reference_ids' => ['<mislinked@example.com>'],
    ]);

    $this->get('/admin/mail/'.rawurlencode(EmailFolderEnum::INBOX->value).'/'.$mislinked->id)
        ->assertOk()
        ->assertSee(__('admin::app.mail.view.thread.subject').':', false)
        ->assertSee('Behandelingsovereenkomst', false)
        ->assertSee('Medicatieoverzicht M.A. Bondrager', false)
        ->assertSee(__('admin::app.mail.view.thread.detach'), false)
        ->assertSee('detachFromThread()', false)
        ->assertDontSee(__('admin::app.mail.view.thread.title'), false);
});
