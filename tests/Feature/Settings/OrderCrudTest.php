<?php

namespace Tests\Feature\Settings;

use App\Models\Order;
use App\Models\SalesLead;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('orders index returns datagrid json', function () {
    $o1 = Order::factory()->create();
    $o2 = Order::factory()->create();

    $response = $this->getJson(route('admin.orders.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($o1->id, $o2->id);
});

test('can create order', function () {
    $salesLead = SalesLead::factory()->create();

    $payload = [
        'title'         => 'Test Order',
        'total_price'   => 123.45,
        'sales_lead_id' => $salesLead->id,
    ];

    $response = $this->postJson(route('admin.orders.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('orders', [
        'title' => 'Test Order',
    ]);
});

test('can update order', function () {
    $order = Order::factory()->create();
    $salesLead = SalesLead::factory()->create();

    $payload = [
        'title'         => 'Updated Order',
        'total_price'   => 999.99,
        'sales_lead_id' => $salesLead->id,
        '_method'       => 'put',
    ];

    $response = $this->postJson(route('admin.orders.update', ['id' => $order->id]), $payload);
    $response->assertOk()->assertJsonPath('data.title', 'Updated Order');

    $this->assertDatabaseHas('orders', [
        'id'    => $order->id,
        'title' => 'Updated Order',
    ]);
});

test('can delete order', function () {
    $order = Order::factory()->create();

    $response = $this->deleteJson(route('admin.orders.delete', ['id' => $order->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('orders', [
        'id' => $order->id,
    ]);
});
