<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(), 'user');

    $this->inbox = Folder::create([
        'name'         => EmailFolderEnum::INBOX->value,
        'parent_id'    => null,
        'order'        => 1,
        'is_deletable' => false,
    ]);

    $this->email = Email::create([
        'subject'   => 'Test Email',
        'from'      => ['test@example.com'],
        'reply'     => 'Test content',
        'folder_id' => $this->inbox->id,
    ]);
});

test('trashing an email moves it to the Trash folder, creating it if missing', function () {
    expect(Folder::where('name', EmailFolderEnum::TRASH->value)->exists())->toBeFalse();

    $this->deleteJson(route('admin.mail.delete', $this->email->id), ['type' => 'trash']);

    $trash = Folder::where('name', EmailFolderEnum::TRASH->value)->first();

    expect($trash)->not->toBeNull();
    expect($this->email->fresh()->folder_id)->toBe($trash->id);
});

test('trashing an email already in Trash deletes it permanently', function () {
    $trash = Folder::create(['name' => EmailFolderEnum::TRASH->value, 'order' => 8, 'is_deletable' => false]);
    $this->email->update(['folder_id' => $trash->id]);

    $this->deleteJson(route('admin.mail.delete', $this->email->id), ['type' => 'trash']);

    expect(Email::find($this->email->id))->toBeNull();
});
