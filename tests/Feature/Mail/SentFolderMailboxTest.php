<?php

use App\Services\Mail\CrmMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Folder;
use Webkul\Email\Repositories\EmailRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    Folder::create([
        'name'         => EmailFolderEnum::SENT_PRIVATESCAN->value,
        'parent_id'    => null,
        'order'        => 1,
        'is_deletable' => false,
    ]);

    Folder::create([
        'name'         => EmailFolderEnum::SENT_HERNIAPOLI->value,
        'parent_id'    => null,
        'order'        => 2,
        'is_deletable' => false,
    ]);

    Folder::create([
        'name'         => EmailFolderEnum::DRAFT->value,
        'parent_id'    => null,
        'order'        => 3,
        'is_deletable' => false,
    ]);
});

test('outbound email is placed in Sent Privatescan when mailbox_key is privatescan', function () {
    Mail::fake();

    $email = app(EmailRepository::class)->create([
        'reply_to'     => ['test@example.com'],
        'reply'        => 'Hello',
        'subject'      => 'Test',
        'mailbox_key'  => 'privatescan',
    ]);

    app(CrmMailService::class)->sendEmail($email->fresh());

    expect($email->fresh()->folder->name)->toBe(EmailFolderEnum::SENT_PRIVATESCAN->value);
});

test('outbound email is placed in Sent HerniaPoli when mailbox_key is herniapoli', function () {
    Mail::fake();

    $email = app(EmailRepository::class)->create([
        'reply_to'     => ['test@example.com'],
        'reply'        => 'Hello',
        'subject'      => 'Test',
        'mailbox_key'  => 'herniapoli',
    ]);

    app(CrmMailService::class)->sendEmail($email->fresh());

    expect($email->fresh()->folder->name)->toBe(EmailFolderEnum::SENT_HERNIAPOLI->value);
});

test('sentFolderNameForMailbox falls back to privatescan sent folder', function () {
    expect(EmailFolderEnum::sentFolderNameForMailbox(null))
        ->toBe(EmailFolderEnum::SENT_PRIVATESCAN->value);
});
