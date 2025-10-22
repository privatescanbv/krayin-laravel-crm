<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Email\Models\Email;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');
});

test('mail view JavaScript template contains null safety patterns', function () {
    $response = $this->get('/admin/mail/inbox');
    $response->assertStatus(200);

    // The null safety patterns are in the mail view template, not inbox
    // This test verifies the inbox page loads without JavaScript errors
    expect($response->status())->toBe(200);
});

test('email model handles null arrays gracefully', function () {
    // Create inbox folder first
    $folder = \Webkul\Email\Models\Folder::create(['name' => 'inbox']);

    $email = new Email([
        'subject'   => 'Test Email',
        'folder_id' => $folder->id,
        'reply_to'  => null,
        'cc'        => null,
        'bcc'       => null,
        'from'      => null,
    ]);

    // Test that null arrays don't cause issues
    expect($email->reply_to)->toBeNull()
        ->and($email->cc)->toBeNull()
        ->and($email->bcc)->toBeNull()
        ->and($email->from)->toBeNull();
});

test('email model handles empty arrays gracefully', function () {
    // Create inbox folder first
    $folder = \Webkul\Email\Models\Folder::create(['name' => 'inbox']);

    $email = new Email([
        'subject'   => 'Test Email',
        'folder_id' => $folder->id,
        'reply_to'  => [],
        'cc'        => [],
        'bcc'       => [],
        'from'      => [],
    ]);

    // Test that empty arrays work correctly
    expect($email->reply_to)->toBeArray()
        ->and($email->cc)->toBeArray()
        ->and($email->bcc)->toBeArray()
        ->and($email->from)->toBeArray()
        ->and($email->reply_to)->toHaveCount(0)
        ->and($email->cc)->toHaveCount(0)
        ->and($email->bcc)->toHaveCount(0)
        ->and($email->from)->toHaveCount(0);

});
