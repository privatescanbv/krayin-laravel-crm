<?php

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;

uses(RefreshDatabase::class);

test('email with order_id appears in order activities endpoint', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $order = Order::factory()->create();

    $folder = Folder::create(['name' => 'sent']);

    $email = Email::create([
        'subject'       => 'Orderbevestiging test',
        'reply'         => '<p>Bevestiging inhoud</p>',
        'from'          => json_encode(['address' => 'noreply@example.com', 'name' => 'Test']),
        'reply_to'      => json_encode(['test@patient.com']),
        'order_id'      => $order->id,
        'sales_lead_id' => $order->sales_lead_id,
        'folder_id'     => $folder->id,
        'source'        => 'system',
    ]);

    $response = $this->getJson(route('admin.orders.activities.index', $order->id));
    $response->assertSuccessful();

    $data = $response->json('data');
    $emailItems = collect($data)->where('type', 'email');

    expect($emailItems)->toHaveCount(1)
        ->and($emailItems->first()['title'])->toContain('Orderbevestiging test');
});

test('email stored via EmailController with order_id gets order_id persisted', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $order = Order::factory()->create();

    $response = $this->post(route('admin.mail.store'), [
        'reply_to'      => ['patient@example.com'],
        'reply'         => '<p>Bevestiging</p>',
        'subject'       => 'Order bevestiging mail',
        'order_id'      => $order->id,
        'sales_lead_id' => $order->sales_lead_id,
    ], [
        'Accept'           => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertSuccessful();

    $email = Email::where('order_id', $order->id)->first();

    expect($email)->not->toBeNull()
        ->and($email->subject)->toBe('Order bevestiging mail');
});

test('email activity shows the actual sender, not the logged-in user', function () {
    // Logged-in user has a recognisable name that must NOT leak onto the email row.
    $user = makeUser(['name' => 'Mark Bulthuis']);
    $this->actingAs($user, 'user');

    $order = Order::factory()->create();

    $folder = Folder::create(['name' => 'inbox']);

    Email::create([
        'subject'       => 'Vraag van patiënt',
        'reply'         => '<p>Inhoud</p>',
        'from'          => json_encode(['name' => 'Jan Patiënt', 'email' => 'jan@patient.com']),
        'reply_to'      => json_encode(['service@privatescan.nl']),
        'order_id'      => $order->id,
        'sales_lead_id' => $order->sales_lead_id,
        'folder_id'     => $folder->id,
        'source'        => 'imap',
    ]);

    $response = $this->getJson(route('admin.orders.activities.index', $order->id));

    // A malformed sender object triggers "Undefined property" warnings inside
    // UserResource that get promoted to an ErrorException (500) during an HTTP
    // request, so a plain successful response already guards that regression.
    $response->assertSuccessful();

    $emailActivity = collect($response->json('data'))->firstWhere('type', 'email');

    expect($emailActivity)->not->toBeNull()
        ->and($emailActivity['user']['name'])->toBe('Jan Patiënt')
        ->and($emailActivity['user']['name'])->not->toBe('Mark Bulthuis');
});

test('email activity falls back to sender address when the from name is empty', function () {
    $user = makeUser(['name' => 'Mark Bulthuis']);
    $this->actingAs($user, 'user');

    $order = Order::factory()->create();

    $folder = Folder::create(['name' => 'sent']);

    Email::create([
        'subject'       => 'Orderbevestiging',
        'reply'         => '<p>Bevestiging</p>',
        'from'          => json_encode(['name' => '', 'email' => 'service@privatescan.nl']),
        'reply_to'      => json_encode(['jan@patient.com']),
        'order_id'      => $order->id,
        'sales_lead_id' => $order->sales_lead_id,
        'folder_id'     => $folder->id,
        'source'        => 'system',
    ]);

    $response = $this->getJson(route('admin.orders.activities.index', $order->id));
    $response->assertSuccessful();

    $emailActivity = collect($response->json('data'))->firstWhere('type', 'email');

    expect($emailActivity['user']['name'])->toBe('service@privatescan.nl');
});

test('order view exposes compose mail recipient hints for the mail action', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $order = Order::factory()->create();

    $response = $this->get(route('admin.orders.view', $order->id));

    $response->assertOk();
    $response->assertViewHas('composeMailEmails', fn ($emails) => is_array($emails));
});
