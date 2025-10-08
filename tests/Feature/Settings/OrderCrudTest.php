<?php

namespace Tests\Feature\Settings;

use App\Models\Order;
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

    $response = $this->getJson(route('admin.settings.orders.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($o1->id, $o2->id);
});

test('can create order', function () {
    $payload = [
        'title'          => 'Test Order',
        'sales_order_id' => 'SO-10001',
        'total_price'    => 123.45,
    ];

    $response = $this->postJson(route('admin.settings.orders.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('orders', [
        'title'          => 'Test Order',
        'sales_order_id' => 'SO-10001',
    ]);
});

test('can update order', function () {
    $order = Order::factory()->create();

    $payload = [
        'title'          => 'Updated Order',
        'sales_order_id' => 'SO-99999',
        'total_price'    => 999.99,
        '_method'        => 'put',
    ];

    $response = $this->postJson(route('admin.settings.orders.update', ['id' => $order->id]), $payload);
    $response->assertOk()->assertJsonPath('data.title', 'Updated Order');

    $this->assertDatabaseHas('orders', [
        'id'             => $order->id,
        'title'          => 'Updated Order',
        'sales_order_id' => 'SO-99999',
    ]);
});

test('can delete order', function () {
    $order = Order::factory()->create();

    $response = $this->deleteJson(route('admin.settings.orders.delete', ['id' => $order->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('orders', [
        'id' => $order->id,
    ]);
});

