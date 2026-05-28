<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Webkul\User\Models\Role;

it('rejects tinymce uploads when images are disabled', function () {
    Config::set('tinymce.images_enabled', false);

    $admin = makeUser(['role_id' => Role::factory()->create(['permission_type' => 'all'])->id]);

    $this->actingAs($admin, 'user')
        ->post(route('admin.tinymce.upload'), [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])
        ->assertForbidden();
});

it('accepts tinymce uploads when images are enabled', function () {
    Config::set('tinymce.images_enabled', true);
    Storage::fake('public');

    $admin = makeUser(['role_id' => Role::factory()->create(['permission_type' => 'all'])->id]);

    $this->actingAs($admin, 'user')
        ->post(route('admin.tinymce.upload'), [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])
        ->assertOk()
        ->assertJsonStructure(['location']);
});
