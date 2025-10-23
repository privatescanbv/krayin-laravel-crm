<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');
    
    // Create test folders
    $this->inboxFolder = Folder::create([
        'name' => 'inbox',
        'parent_id' => null,
        'order' => 1,
        'is_deletable' => false,
    ]);
    
    $this->archiveFolder = Folder::create([
        'name' => 'archive',
        'parent_id' => null,
        'order' => 2,
        'is_deletable' => false,
    ]);
    
    // Create test email
    $this->email = Email::create([
        'subject' => 'Test Email',
        'from' => ['test@example.com'],
        'reply' => 'Test content',
        'folder_id' => $this->inboxFolder->id,
    ]);
});

test('can move email to another folder', function () {
    $response = $this->post(route('admin.mail.move', $this->email->id), [
        'folder_id' => $this->archiveFolder->id,
    ]);
    
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Email moved successfully.',
            'data' => [
                'folder_name' => 'archive',
            ],
        ]);
    
    $this->email->refresh();
    expect($this->email->folder_id)->toBe($this->archiveFolder->id);
});

test('cannot move email with invalid folder id', function () {
    $response = $this->post(route('admin.mail.move', $this->email->id), [
        'folder_id' => 999, // Non-existent folder
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['folder_id']);
});

test('cannot move email without folder id', function () {
    $response = $this->post(route('admin.mail.move', $this->email->id), []);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['folder_id']);
});

test('cannot move non-existent email', function () {
    $response = $this->post(route('admin.mail.move', 999), [
        'folder_id' => $this->archiveFolder->id,
    ]);
    
    $response->assertStatus(404);
});
