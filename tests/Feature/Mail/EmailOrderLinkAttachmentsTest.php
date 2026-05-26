<?php

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Webkul\Email\Models\Attachment;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake();
    $this->actingAs(makeUser(), 'user');
    Folder::create(['name' => 'inbox']);
    Folder::create(['name' => 'verwerkt']);
});

test('linking email to order preserves attachments in database', function () {
    $order = Order::factory()->create();
    $folder = Folder::where('name', 'inbox')->first();

    $email = Email::create([
        'subject'   => 'Order mail with attachment',
        'reply'     => '<p>Body</p>',
        'from'      => ['address' => 'patient@example.com', 'name' => 'Patient'],
        'reply_to'  => ['patient@example.com'],
        'folder_id' => $folder->id,
        'source'    => 'email',
    ]);

    $path = 'emails/'.$email->id.'/document.pdf';
    Storage::put($path, 'pdf-content');

    Attachment::create([
        'email_id' => $email->id,
        'name'     => 'document.pdf',
        'path'     => $path,
    ]);

    $this->put(route('admin.mail.update', $email->id), [
        'order_id' => $order->id,
    ])->assertRedirect();

    expect(Attachment::where('email_id', $email->id)->count())->toBe(1)
        ->and($email->refresh()->order_id)->toBe($order->id);
});

test('order activities include attachments from parent when latest reply has none', function () {
    $order = Order::factory()->create();
    $folder = Folder::where('name', 'inbox')->first();

    $parent = Email::create([
        'subject'   => 'Original with attachment',
        'reply'     => '<p>Original body</p>',
        'from'      => ['address' => 'patient@example.com', 'name' => 'Patient'],
        'reply_to'  => ['patient@example.com'],
        'folder_id' => $folder->id,
        'source'    => 'email',
    ]);

    $path = 'emails/'.$parent->id.'/scan.pdf';
    Storage::put($path, 'scan-content');

    Attachment::create([
        'email_id' => $parent->id,
        'name'     => 'scan.pdf',
        'path'     => $path,
    ]);

    $child = Email::create([
        'subject'   => 'Re: Original with attachment',
        'reply'     => '<p>Reply without attachment</p>',
        'from'      => ['address' => 'agent@example.com', 'name' => 'Agent'],
        'reply_to'  => ['patient@example.com'],
        'folder_id' => $folder->id,
        'source'    => 'email',
        'parent_id' => $parent->id,
    ]);

    $this->put(route('admin.mail.update', $parent->id), [
        'order_id' => $order->id,
    ]);

    $response = $this->getJson(route('admin.orders.activities.index', $order->id));
    $response->assertSuccessful();

    $emailActivity = collect($response->json('data'))->firstWhere('type', 'email');

    expect($emailActivity)->not->toBeNull()
        ->and($emailActivity['id'])->toBe($child->id)
        ->and($emailActivity['files'])->toHaveCount(1)
        ->and($emailActivity['files'][0]['name'])->toBe('scan.pdf');
});

test('mail view resolves thread root so child url still shows parent attachments', function () {
    $order = Order::factory()->create();
    $folder = Folder::where('name', 'inbox')->first();

    $parent = Email::create([
        'subject'   => 'Thread root',
        'reply'     => '<p>Root body</p>',
        'from'      => ['address' => 'patient@example.com', 'name' => 'Patient'],
        'reply_to'  => ['patient@example.com'],
        'folder_id' => $folder->id,
        'source'    => 'email',
    ]);

    $path = 'emails/'.$parent->id.'/root.pdf';
    Storage::put($path, 'root-content');

    Attachment::create([
        'email_id' => $parent->id,
        'name'     => 'root.pdf',
        'path'     => $path,
    ]);

    $child = Email::create([
        'subject'   => 'Re: Thread root',
        'reply'     => '<p>Child reply</p>',
        'from'      => ['address' => 'agent@example.com', 'name' => 'Agent'],
        'reply_to'  => ['patient@example.com'],
        'folder_id' => $folder->id,
        'source'    => 'email',
        'parent_id' => $parent->id,
    ]);

    $this->put(route('admin.mail.update', $child->id), [
        'order_id' => $order->id,
    ])->assertRedirect();

    $response = $this->get(route('admin.mail.view', ['route' => 'inbox', 'id' => $child->id]));

    $response->assertOk();
    $response->assertSee('root.pdf', false);

    expect($parent->refresh()->order_id)->toBe($order->id)
        ->and($child->refresh()->order_id)->toBe($order->id);
});

test('linking order propagates order_id to entire thread', function () {
    $order = Order::factory()->create();
    $folder = Folder::where('name', 'inbox')->first();

    $parent = Email::create([
        'subject'   => 'Parent',
        'reply'     => 'Body',
        'from'      => ['address' => 'patient@example.com', 'name' => 'Patient'],
        'reply_to'  => ['patient@example.com'],
        'folder_id' => $folder->id,
        'source'    => 'email',
    ]);

    $child = Email::create([
        'subject'   => 'Re: Parent',
        'reply'     => 'Reply',
        'from'      => ['address' => 'agent@example.com', 'name' => 'Agent'],
        'reply_to'  => ['patient@example.com'],
        'folder_id' => $folder->id,
        'source'    => 'email',
        'parent_id' => $parent->id,
    ]);

    $this->put(route('admin.mail.update', $parent->id), [
        'order_id' => $order->id,
    ]);

    expect($parent->refresh()->order_id)->toBe($order->id)
        ->and($child->refresh()->order_id)->toBe($order->id);
});
