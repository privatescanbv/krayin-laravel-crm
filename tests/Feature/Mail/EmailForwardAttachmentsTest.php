<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Webkul\Email\Models\Attachment;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Installer\Http\Middleware\CanInstall;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->actingAs(makeUser(), 'user');
    Storage::fake();
    Mail::fake();

    Folder::create(['name' => 'sent']);
    Folder::create(['name' => 'draft']);
});

test('forward copies attachments from source email on store', function () {
    $sourceEmail = Email::create([
        'subject'    => 'Origineel met bijlage',
        'reply'      => '<p>Inhoud</p>',
        'from'       => json_encode(['email' => 'sender@example.com', 'name' => 'Sender']),
        'reply_to'   => json_encode(['recipient@example.com']),
        'unique_id'  => 'source-1',
        'message_id' => 'source-1',
        'source'     => 'email',
    ]);

    Storage::put('emails/'.$sourceEmail->id.'/document.pdf', 'pdf-content');

    Attachment::create([
        'email_id'     => $sourceEmail->id,
        'name'         => 'document.pdf',
        'path'         => 'emails/'.$sourceEmail->id.'/document.pdf',
        'content_type' => 'application/pdf',
        'size'         => 11,
    ]);

    $response = $this->post(route('admin.mail.store'), [
        'reply_to'              => ['forward@example.com'],
        'reply'                 => '<p>Doorgestuurd bericht</p>',
        'subject'               => 'Fwd: Origineel met bijlage',
        'email_action'          => 'forward',
        'forward_from_email_id' => $sourceEmail->id,
        'parent_id'             => $sourceEmail->id,
    ], [
        'Accept'           => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertSuccessful();

    $forwardedEmail = Email::query()->where('id', '!=', $sourceEmail->id)->latest('id')->first();

    expect($forwardedEmail)->not->toBeNull()
        ->and($forwardedEmail->attachments)->toHaveCount(1)
        ->and($forwardedEmail->attachments->first()->name)->toBe('document.pdf');

    Storage::assertExists($forwardedEmail->attachments->first()->path);
});

test('reply does not copy attachments from source email', function () {
    $sourceEmail = Email::create([
        'subject'    => 'Origineel',
        'reply'      => '<p>Inhoud</p>',
        'from'       => json_encode(['email' => 'sender@example.com', 'name' => 'Sender']),
        'reply_to'   => json_encode(['recipient@example.com']),
        'unique_id'  => 'source-2',
        'message_id' => 'source-2',
        'source'     => 'email',
    ]);

    Storage::put('emails/'.$sourceEmail->id.'/document.pdf', 'pdf-content');

    Attachment::create([
        'email_id'     => $sourceEmail->id,
        'name'         => 'document.pdf',
        'path'         => 'emails/'.$sourceEmail->id.'/document.pdf',
        'content_type' => 'application/pdf',
        'size'         => 11,
    ]);

    $response = $this->post(route('admin.mail.store'), [
        'reply_to'              => ['reply@example.com'],
        'reply'                 => '<p>Antwoord</p>',
        'subject'               => 'Re: Origineel',
        'email_action'          => 'reply',
        'forward_from_email_id' => $sourceEmail->id,
        'parent_id'             => $sourceEmail->id,
    ], [
        'Accept'           => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertSuccessful();

    $replyEmail = Email::query()->where('id', '!=', $sourceEmail->id)->latest('id')->first();

    expect($replyEmail->attachments)->toHaveCount(0);
});

test('attachment repository copies files to target email directory', function () {
    $sourceEmail = Email::create([
        'subject'    => 'Bron',
        'unique_id'  => 'source-3',
        'message_id' => 'source-3',
    ]);
    $targetEmail = Email::create([
        'subject'    => 'Doel',
        'unique_id'  => 'target-3',
        'message_id' => 'target-3',
    ]);

    Storage::put('emails/'.$sourceEmail->id.'/report.pdf', 'report bytes');

    Attachment::create([
        'email_id'     => $sourceEmail->id,
        'name'         => 'report.pdf',
        'path'         => 'emails/'.$sourceEmail->id.'/report.pdf',
        'content_type' => 'application/pdf',
        'size'         => 12,
    ]);

    app(AttachmentRepository::class)->copyAttachmentsToEmail($targetEmail, $sourceEmail);

    $copied = Attachment::where('email_id', $targetEmail->id)->first();

    expect($copied)->not->toBeNull()
        ->and($copied->path)->toBe('emails/'.$targetEmail->id.'/report.pdf');
    Storage::assertExists($copied->path);
});
