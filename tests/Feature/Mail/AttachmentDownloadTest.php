<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Webkul\Email\Models\Attachment;
use Webkul\Email\Models\Email;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Installer\Http\Middleware\CanInstall;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('createFromGraphData slaat bestand op en zet path op het record', function () {
    Storage::fake();

    $email = Email::create(['subject' => 'Test', 'unique_id' => 'test-graph-1', 'message_id' => 'test-graph-1']);

    $repository = app(AttachmentRepository::class);
    $repository->createFromGraphData($email, [
        'name'         => 'document.pdf',
        'contentType'  => 'application/pdf',
        'contentBytes' => base64_encode('fake pdf content'),
        'size'         => 16,
    ]);

    $attachment = Attachment::where('email_id', $email->id)->first();

    expect($attachment)->not->toBeNull()
        ->and($attachment->path)->toBe('emails/'.$email->id.'/document.pdf')
        ->and($attachment->name)->toBe('document.pdf')
        ->and($attachment->content_type)->toBe('application/pdf');
    Storage::assertExists($attachment->path);
});

test('bijlage met geldig path kan worden gedownload', function () {
    Storage::fake();

    $email = Email::create(['subject' => 'Test', 'unique_id' => 'test-graph-2', 'message_id' => 'test-graph-2']);
    Storage::put('emails/'.$email->id.'/report.pdf', 'PDF content here');

    $attachment = Attachment::create([
        'email_id'     => $email->id,
        'name'         => 'report.pdf',
        'path'         => 'emails/'.$email->id.'/report.pdf',
        'content_type' => 'application/pdf',
        'size'         => 16,
    ]);

    $response = $this->get(route('admin.mail.attachment_download', ['id' => $attachment->id]));
    $response->assertSuccessful();
});

test('bijlage met leeg path redirectt met foutmelding', function () {
    $email = Email::create(['subject' => 'Test', 'unique_id' => 'test-graph-3', 'message_id' => 'test-graph-3']);

    $attachment = Attachment::create([
        'email_id'     => $email->id,
        'name'         => 'missing.pdf',
        'path'         => '',
        'content_type' => 'application/pdf',
        'size'         => 0,
    ]);

    $response = $this->get(route('admin.mail.attachment_download', ['id' => $attachment->id]));
    $response->assertRedirect();
    $response->assertSessionHas('error');
});
