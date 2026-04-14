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
