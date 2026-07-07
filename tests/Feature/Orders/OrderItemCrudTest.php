<?php

namespace Tests\Feature\Settings;

use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ResourceType;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $this->seed(TestSeeder::class);

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

test('can update order_item with empty total_price', function () {
    $product = Product::factory()->create();
    $item = OrderItem::factory()->create(['product_id' => $product->id, 'total_price' => 100]);

    $payload = [
        'order_id'    => $item->order_id,
        'product_id'  => $item->product_id,
        'person_id'   => test()->person->id,
        'quantity'    => 3,
        'total_price' => '',
        '_method'     => 'put',
    ];

    $response = $this->postJson(route('admin.order_items.update', ['id' => $item->id]), $payload);
    $response->assertOk()->assertJsonPath('data.quantity', 3);

    $this->assertDatabaseHas('order_items', [
        'id'          => $item->id,
        'quantity'    => 3,
        'total_price' => 0,
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

test('cannot set order_item status to gewonnen when order stage is before uitgevoerd', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
    ]);
    $item = OrderItem::factory()->create(['order_id' => $order->id]);

    $payload = [
        'order_id'   => $order->id,
        'product_id' => $item->product_id,
        'person_id'  => test()->person->id,
        'quantity'   => 1,
        'status'     => OrderItemStatus::WON->value,
        '_method'    => 'put',
    ];

    $response = $this->postJson(route('admin.order_items.update', ['id' => $item->id]), $payload);
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['status']);
});

test('can not set order_item status to gewonnen when order stage is uitgevoerd', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
    ]);
    $item = OrderItem::factory()->create(['order_id' => $order->id]);

    $payload = [
        'order_id'   => $order->id,
        'product_id' => $item->product_id,
        'person_id'  => test()->person->id,
        'quantity'   => 1,
        'status'     => OrderItemStatus::WON->value,
        '_method'    => 'put',
    ];

    $response = $this->postJson(route('admin.order_items.update', ['id' => $item->id]), $payload);
    $response->assertUnprocessable();
});

test('can not set order_item status to gewonnen when hernia order stage is uitgevoerd', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING_HERNIA->id(),
    ]);
    $item = OrderItem::factory()->create(['order_id' => $order->id]);

    $payload = [
        'order_id'   => $order->id,
        'product_id' => $item->product_id,
        'person_id'  => test()->person->id,
        'quantity'   => 1,
        'status'     => OrderItemStatus::WON->value,
        '_method'    => 'put',
    ];

    $response = $this->postJson(route('admin.order_items.update', ['id' => $item->id]), $payload);
    $response->assertUnprocessable();
});
