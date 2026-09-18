<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Installer\Http\Middleware\CanInstall;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->actingAs(makeUser(), 'user');
    Storage::fake();
    Mail::fake();

    Folder::create(['name' => 'Draft']);
    Folder::create(['name' => 'Sent Privatescan']);
});

test('attachment chips copy files onto a native file input so the mail form can submit them', function () {
    $html = Blade::render('
        <x-admin::attachments allow-multiple="true" />
        @stack("scripts")
    ');

    expect($html)->toContain('id="v-attachment-item-template"');

    preg_match('/id="v-attachment-item-template"[^>]*>(.*?)<\/script>/s', $html, $matches);

    $itemTemplate = $matches[1] ?? '';

    expect($itemTemplate)->not->toBeEmpty()
        ->and($itemTemplate)->toContain('type="file"')
        ->and($itemTemplate)->toContain("name + '[]'")
        ->and($itemTemplate)->not->toContain('v-file-dropzone');
});

test('composing mail with uploaded attachments stores them on the email', function () {
    $file = UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf');

    $response = $this->post(route('admin.mail.store'), [
        'reply_to'    => ['patient@example.com'],
        'reply'       => '<p>Zie bijlage</p>',
        'subject'     => 'Factuur',
        'attachments' => [$file],
    ], [
        'Accept'           => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertSuccessful();

    $email = Email::query()->latest('id')->first();

    expect($email)->not->toBeNull()
        ->and($email->attachments)->toHaveCount(1)
        ->and($email->attachments->first()->name)->toBe('invoice.pdf');

    Storage::assertExists($email->attachments->first()->path);
});

test('composing mail with multiple uploaded attachments stores all of them', function () {
    $files = [
        UploadedFile::fake()->create('scan.pdf', 12, 'application/pdf'),
        UploadedFile::fake()->create('notes.txt', 4, 'text/plain'),
    ];

    $response = $this->post(route('admin.mail.store'), [
        'reply_to'    => ['patient@example.com'],
        'reply'       => '<p>Twee bijlagen</p>',
        'subject'     => 'Documenten',
        'attachments' => $files,
    ], [
        'Accept'           => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertSuccessful();

    $email = Email::query()->latest('id')->first();

    expect($email->attachments)->toHaveCount(2)
        ->and($email->attachments->pluck('name')->all())->toEqualCanonicalizing([
            'scan.pdf',
            'notes.txt',
        ]);
});
