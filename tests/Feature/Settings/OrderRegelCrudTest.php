THIS SHOULD BE A LINTER ERROR<?php

namespace Tests\Feature\Settings;

use App\Models\Order;
use App\Models\OrderRegel;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('order_regels index returns datagrid json', function () {
    $o = Order::factory()->create();
    $r1 = OrderRegel::factory()->create(['order_id' => $o->id]);
    $r2 = OrderRegel::factory()->create(['order_id' => $o->id]);

    $response = $this->getJson(route('admin.order_regels.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($r1->id, $r2->id);
});

test('can create order_regel', function () {
    $o = Order::factory()->create();
    $p = Product::factory()->create();

    $payload = [
        'order_id'    => $o->id,
        'product_id'  => $p->id,
        'quantity'    => 2,
        'total_price' => 200,
    ];

    $response = $this->postJson(route('admin.order_regels.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('order_regels', [
        'order_id'   => $o->id,
        'product_id' => $p->id,
        'quantity'   => 2,
    ]);
});

test('can update order_regel', function () {
    $regel = OrderRegel::factory()->create();

    $payload = [
        'order_id'    => $regel->order_id,
        'product_id'  => $regel->product_id,
        'quantity'    => 5,
        'total_price' => 555.55,
        '_method'     => 'put',
    ];

    $response = $this->postJson(route('admin.order_regels.update', ['id' => $regel->id]), $payload);
    $response->assertOk()->assertJsonPath('data.quantity', 5);

    $this->assertDatabaseHas('order_regels', [
        'id'       => $regel->id,
        'quantity' => 5,
    ]);
});

test('can delete order_regel', function () {
    $regel = OrderRegel::factory()->create();

    $response = $this->deleteJson(route('admin.order_regels.delete', ['id' => $regel->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('order_regels', [
        'id' => $regel->id,
    ]);
});
