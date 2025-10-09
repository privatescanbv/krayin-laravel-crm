<?php

namespace Tests\Feature\Settings;

use App\Models\Order;
use App\Models\OrderItem;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('order_items index returns datagrid json', function () {
    $o = Order::factory()->create();
    $r1 = OrderItem::factory()->create(['order_id' => $o->id]);
    $r2 = OrderItem::factory()->create(['order_id' => $o->id]);

    $response = $this->getJson(route('admin.order_items.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($r1->id, $r2->id);
});

test('can create order_item', function () {
    $o = Order::factory()->create();
    $p = Product::factory()->create();

    $payload = [
        'order_id'    => $o->id,
        'product_id'  => $p->id,
        'quantity'    => 2,
        'total_price' => 200,
    ];

    $response = $this->postJson(route('admin.order_items.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('order_items', [
        'order_id'   => $o->id,
        'product_id' => $p->id,
        'quantity'   => 2,
    ]);
});

test('can update order_item', function () {
    $item = OrderItem::factory()->create();

    $payload = [
        'order_id'    => $item->order_id,
        'product_id'  => $item->product_id,
        'quantity'    => 5,
        'total_price' => 555.55,
        '_method'     => 'put',
    ];

    $response = $this->postJson(route('admin.order_items.update', ['id' => $item->id]), $payload);
    $response->assertOk()->assertJsonPath('data.quantity', 5);

    $this->assertDatabaseHas('order_items', [
        'id'       => $item->id,
        'quantity' => 5,
    ]);
});

test('can delete order_item', function () {
    $item = OrderItem::factory()->create();

    $response = $this->deleteJson(route('admin.order_items.delete', ['id' => $item->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('order_items', [
        'id' => $item->id,
    ]);
});
