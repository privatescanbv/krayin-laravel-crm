<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');

    // Create test folders with hierarchical structure
    $this->inboxFolder = Folder::create([
        'name'         => 'inbox',
        'parent_id'    => null,
        'order'        => 1,
        'is_deletable' => false,
    ]);

    $this->archiveFolder = Folder::create([
        'name'         => 'archive',
        'parent_id'    => null,
        'order'        => 2,
        'is_deletable' => false,
    ]);

    $this->sentFolder = Folder::create([
        'name'         => 'sent',
        'parent_id'    => null,
        'order'        => 3,
        'is_deletable' => false,
    ]);

    // Create child folder
    $this->importantFolder = Folder::create([
        'name'         => 'important',
        'parent_id'    => $this->inboxFolder->id,
        'order'        => 1,
        'is_deletable' => true,
    ]);

    // Create test email
    $this->email = Email::create([
        'subject'   => 'Test Email',
        'from'      => ['test@example.com'],
        'reply'     => 'Test content',
        'folder_id' => $this->inboxFolder->id,
    ]);
});

test('can move email to another folder', function () {
    $response = $this->post(route('admin.mail.move', $this->email->id), [
        'folder_id' => $this->archiveFolder->id,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'folder_name',
            ],
        ]);

    $this->email->refresh();
    expect($this->email->folder_id)->toBe($this->archiveFolder->id);
});

test('can move email to child folder', function () {
    $response = $this->post(route('admin.mail.move', $this->email->id), [
        'folder_id' => $this->importantFolder->id,
    ]);

    $response->assertStatus(200);

    $this->email->refresh();
    expect($this->email->folder_id)->toBe($this->importantFolder->id);
});

test('can move email between different parent folders', function () {
    // Move from inbox to sent
    $response = $this->post(route('admin.mail.move', $this->email->id), [
        'folder_id' => $this->sentFolder->id,
    ]);

    $response->assertStatus(200);

    $this->email->refresh();
    expect($this->email->folder_id)->toBe($this->sentFolder->id);

    // Move from sent to archive
    $response = $this->post(route('admin.mail.move', $this->email->id), [
        'folder_id' => $this->archiveFolder->id,
    ]);

    $response->assertStatus(200);

    $this->email->refresh();
    expect($this->email->folder_id)->toBe($this->archiveFolder->id);
});

test('cannot move email with invalid folder id', function () {
    $response = $this->post(route('admin.mail.move', $this->email->id), [
        'folder_id' => 999, // Non-existent folder
    ]);

    // The application redirects on validation errors, so we check for redirect
    $response->assertStatus(302);

    // Verify the email folder hasn't changed
    $this->email->refresh();
    expect($this->email->folder_id)->toBe($this->inboxFolder->id);
});

test('cannot move email without folder id', function () {
    $response = $this->post(route('admin.mail.move', $this->email->id), []);

    // The application redirects on validation errors, so we check for redirect
    $response->assertStatus(302);

    // Verify the email folder hasn't changed
    $this->email->refresh();
    expect($this->email->folder_id)->toBe($this->inboxFolder->id);
});

test('cannot move email with non-integer folder id', function () {
    $response = $this->post(route('admin.mail.move', $this->email->id), [
        'folder_id' => 'invalid',
    ]);

    // The application redirects on validation errors, so we check for redirect
    $response->assertStatus(302);

    // Verify the email folder hasn't changed
    $this->email->refresh();
    expect($this->email->folder_id)->toBe($this->inboxFolder->id);
});

test('cannot move non-existent email', function () {
    $response = $this->post(route('admin.mail.move', 999), [
        'folder_id' => $this->archiveFolder->id,
    ]);

    $response->assertStatus(404);
});

test('email folder relationship is properly updated', function () {
    // Verify initial folder relationship
    expect($this->email->folder_id)->toBe($this->inboxFolder->id);
    expect($this->email->folder->name)->toBe('inbox');

    // Move email
    $response = $this->post(route('admin.mail.move', $this->email->id), [
        'folder_id' => $this->archiveFolder->id,
    ]);

    $response->assertStatus(200);

    // Refresh and verify new relationship
    $this->email->refresh();
    expect($this->email->folder_id)->toBe($this->archiveFolder->id);
    expect($this->email->folder->name)->toBe('archive');
});

test('can move email to same folder without error', function () {
    $response = $this->post(route('admin.mail.move', $this->email->id), [
        'folder_id' => $this->inboxFolder->id, // Same folder
    ]);

    $response->assertStatus(200);

    $this->email->refresh();
    expect($this->email->folder_id)->toBe($this->inboxFolder->id);
});

test('move email updates folder relationship correctly', function () {
    // Create another email in different folder
    $anotherEmail = Email::create([
        'subject'   => 'Another Test Email',
        'from'      => ['another@example.com'],
        'reply'     => 'Another test content',
        'folder_id' => $this->sentFolder->id,
    ]);

    // Move first email to sent folder
    $response = $this->post(route('admin.mail.move', $this->email->id), [
        'folder_id' => $this->sentFolder->id,
    ]);

    $response->assertStatus(200);

    // Both emails should now be in sent folder
    $this->email->refresh();
    $anotherEmail->refresh();

    expect($this->email->folder_id)->toBe($this->sentFolder->id);
    expect($anotherEmail->folder_id)->toBe($this->sentFolder->id);
    expect($this->email->folder->name)->toBe('sent');
    expect($anotherEmail->folder->name)->toBe('sent');
});
