<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;

uses(RefreshDatabase::class);

test('email model handles null created_at gracefully', function () {
    // Create inbox folder first
    $folder = Folder::create(['name' => 'inbox']);

    // Create email with null created_at
    $email = new Email([
        'subject'    => 'Test Email',
        'from'       => ['test@example.com'],
        'reply'      => 'Test content',
        'folder_id'  => $folder->id,
        'created_at' => null,
    ]);

    // Save without timestamps to avoid auto-setting created_at
    $email->save(['timestamps' => false]);

    // Test that time_ago attribute doesn't throw exception
    expect($email->time_ago)->toBe('Unknown');
});

test('email model time_ago works with valid created_at', function () {
    // Create inbox folder first
    $folder = Folder::create(['name' => 'inbox']);

    $email = new Email([
        'subject'    => 'Test Email',
        'from'       => ['test@example.com'],
        'reply'      => 'Test content',
        'folder_id'  => $folder->id,
        'created_at' => now(),
    ]);

    // Test that time_ago attribute works with valid timestamp
    expect($email->time_ago)->not->toBe('Unknown')
        ->and($email->time_ago)->toBeString();
});

test('email model handles missing created_at field', function () {
    // Create inbox folder first
    $folder = Folder::create(['name' => 'inbox']);

    // Create email without created_at field
    $email = new Email([
        'subject'   => 'Test Email',
        'from'      => ['test@example.com'],
        'reply'     => 'Test content',
        'folder_id' => $folder->id,
    ]);

    // Manually set created_at to null
    $email->created_at = null;

    // Test that time_ago attribute doesn't throw exception
    expect($email->time_ago)->toBe('Unknown');
});

test('email model created_at is properly cast', function () {
    // Create inbox folder first
    $folder = Folder::create(['name' => 'inbox']);

    $email = new Email([
        'subject'    => 'Test Email',
        'from'       => ['test@example.com'],
        'reply'      => 'Test content',
        'folder_id'  => $folder->id,
        'created_at' => now(),
    ]);

    // Test that created_at is properly cast to Carbon instance
    expect($email->created_at)->not->toBeNull()
        ->and($email->created_at)->toBeInstanceOf(Carbon::class);
});

test('email model handles edge cases for time_ago', function () {
    // Create inbox folder first
    $folder = Folder::create(['name' => 'inbox']);

    $email = new Email([
        'subject'   => 'Test Email',
        'from'      => ['test@example.com'],
        'reply'     => 'Test content',
        'folder_id' => $folder->id,
    ]);

    // Test with different null scenarios
    $email->created_at = null;
    expect($email->time_ago)->toBe('Unknown');

    // Test with valid timestamp
    $email->created_at = now();
    expect($email->time_ago)->not->toBe('Unknown')
        ->and($email->time_ago)->toBeString();
});
