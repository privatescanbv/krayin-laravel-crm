<?php

use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('total_price includes newly created order item', function () {
    $order = Order::factory()->create(['total_price' => 0]);

    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 150.00,
        'status'      => OrderItemStatus::NEW->value,
    ]);

    expect((float) $order->fresh()->total_price)->toBe(150.0);
});

test('total_price is the sum of the lines when the only item is LOST, payable total is 0', function () {
    $order = Order::factory()->create(['total_price' => 0]);

    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 200.00,
        'status'      => OrderItemStatus::LOST->value,
    ]);

    expect((float) $order->fresh()->total_price)->toBe(200.0)
        ->and($order->fresh()->payableTotal())->toBe(0.0);
});

test('total_price keeps the line when the only order item is set to LOST, payable total drops it', function () {
    $order = Order::factory()->create(['total_price' => 0]);
    $item = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 300.00,
        'status'      => OrderItemStatus::NEW->value,
    ]);

    expect((float) $order->fresh()->total_price)->toBe(300.0);

    $item->update(['status' => OrderItemStatus::LOST->value]);

    expect((float) $order->fresh()->total_price)->toBe(300.0)
        ->and($order->fresh()->payableTotal())->toBe(0.0);
});

test('total_price updates when order item total_price changes', function () {
    $order = Order::factory()->create(['total_price' => 0]);
    $item = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 100.00,
        'status'      => OrderItemStatus::NEW->value,
    ]);

    expect((float) $order->fresh()->total_price)->toBe(100.0);

    $item->update(['total_price' => 250.00]);

    expect((float) $order->fresh()->total_price)->toBe(250.0);
});

test('total_price sums only non-LOST items when the order still has active lines', function () {
    $order = Order::factory()->create(['total_price' => 0]);

    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 100.00,
        'status'      => OrderItemStatus::NEW->value,
    ]);
    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 200.00,
        'status'      => OrderItemStatus::PLANNED->value,
    ]);
    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 999.00,
        'status'      => OrderItemStatus::LOST->value,
    ]);

    expect((float) $order->fresh()->total_price)->toBe(300.0)
        ->and($order->fresh()->payableTotal())->toBe(300.0)
        ->and($order->fresh()->load('orderItems')->payableTotal())->toBe(300.0);
});

test('total_price decreases when order item is deleted', function () {
    $order = Order::factory()->create(['total_price' => 0]);
    $item = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 400.00,
        'status'      => OrderItemStatus::NEW->value,
    ]);

    expect((float) $order->fresh()->total_price)->toBe(400.0);

    $item->delete();

    expect((float) $order->fresh()->total_price)->toBe(0.0);
});

test('total_price is kept when order pipeline stage is set to verloren', function () {
    $order = Order::factory()->create(['total_price' => 0]);

    $item1 = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 250.00,
        'status'      => OrderItemStatus::NEW->value,
    ]);
    $item2 = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 150.00,
        'status'      => OrderItemStatus::PLANNED->value,
    ]);

    $order->fresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);

    expect($item1->fresh()->status)->toBe(OrderItemStatus::LOST)
        ->and($item2->fresh()->status)->toBe(OrderItemStatus::LOST)
        ->and((float) DB::table('orders')->where('id', $order->id)->value('total_price'))->toBe(400.0)
        ->and($order->fresh()->payableTotal())->toBe(0.0);
});

test('editing an order item of a lost order updates the lost total_price', function () {
    $order = Order::factory()->create(['total_price' => 0]);
    $item = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 300.00,
        'status'      => OrderItemStatus::NEW->value,
    ]);
    $order->fresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);

    $item->fresh()->update(['total_price' => 350.00]);

    expect((float) $order->fresh()->total_price)->toBe(350.0)
        ->and($order->fresh()->payableTotal())->toBe(0.0);
});

test('total_price is the sum of the lines when all items are LOST', function () {
    $order = Order::factory()->create(['total_price' => 500]);

    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 500.00,
        'status'      => OrderItemStatus::LOST->value,
    ]);

    expect((float) $order->fresh()->total_price)->toBe(500.0)
        ->and($order->fresh()->payableTotal())->toBe(0.0);
});

test('total_price drops a LOST line on a won order but keeps it once all lines are LOST', function () {
    $order = Order::factory()->create(['pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id()]);
    $won = OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 750, 'status' => OrderItemStatus::WON->value]);
    $lost = OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 59, 'status' => OrderItemStatus::WON->value]);

    $lost->update(['status' => OrderItemStatus::LOST->value]);
    expect((float) $order->fresh()->total_price)->toBe(750.0);

    $won->update(['status' => OrderItemStatus::LOST->value]);
    expect((float) $order->fresh()->total_price)->toBe(809.0)
        ->and($order->fresh()->payableTotal())->toBe(0.0);
});
