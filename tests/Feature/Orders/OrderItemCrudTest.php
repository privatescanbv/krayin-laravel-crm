<?php

namespace Tests\Feature\Settings;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ResourceType;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');

    test()->person = Person::factory()->create();
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
        'order_id'      => $o->id,
        'product_id'    => $p->id,
        'person_id'     => test()->person->id,
        'quantity'      => 2,
        'total_price'   => 200,
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
    $rtA = ResourceType::factory()->create();
    $product = Product::factory()->create(['resource_type_id' => $rtA->id]);
    $item = OrderItem::factory()->create(['product_id' => $product->id]);

    $payload = [
        'order_id'          => $item->order_id,
        'product_id'        => $item->product_id,
        'resource_type_id'  => $rtA->id,
        'person_id'         => test()->person->id,
        'quantity'          => 5,
        'total_price'       => 555.55,
        '_method'           => 'put',
    ];

    $response = $this->postJson(route('admin.order_items.update', ['id' => $item->id]), $payload);
    $response->assertOk()->assertJsonPath('data.quantity', 5);

    $this->assertDatabaseHas('order_items', [
        'id'                 => $item->id,
        'quantity'           => 5,
        'resource_type_id'   => null,
    ]);
});

test('order_item resource_type_id is stored when different from product', function () {
    $rtA = ResourceType::factory()->create();
    $rtB = ResourceType::factory()->create();

    $product = Product::factory()->create(['resource_type_id' => $rtA->id]);
    $item = OrderItem::factory()->create(['product_id' => $product->id]);

    $payload = [
        'order_id'          => $item->order_id,
        'product_id'        => $item->product_id,
        'resource_type_id'  => $rtB->id,
        'person_id'         => test()->person->id,
        'quantity'          => 5,
        'total_price'       => 555.55,
        '_method'           => 'put',
    ];

    $response = $this->postJson(route('admin.order_items.update', ['id' => $item->id]), $payload);
    $response->assertOk()->assertJsonPath('data.resource_type_id', $rtB->id);

    $this->assertDatabaseHas('order_items', [
        'id'                 => $item->id,
        'resource_type_id'   => $rtB->id,
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
