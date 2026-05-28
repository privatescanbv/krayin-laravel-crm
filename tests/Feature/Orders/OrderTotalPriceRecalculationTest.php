<?php

use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('total_price includes newly created order item', function () {
    $order = Order::factory()->create(['total_price' => 0]);

    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 150.00,
        'status'      => OrderItemStatus::NEW->value,
    ]);

    expect((float) $order->fresh()->total_price)->toBe(150.0);
});

test('total_price excludes LOST items on creation', function () {
    $order = Order::factory()->create(['total_price' => 0]);

    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 200.00,
        'status'      => OrderItemStatus::LOST->value,
    ]);

    expect((float) $order->fresh()->total_price)->toBe(0.0);
});

test('total_price decreases when order item is set to LOST', function () {
    $order = Order::factory()->create(['total_price' => 0]);
    $item = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 300.00,
        'status'      => OrderItemStatus::NEW->value,
    ]);

    expect((float) $order->fresh()->total_price)->toBe(300.0);

    $item->update(['status' => OrderItemStatus::LOST->value]);

    expect((float) $order->fresh()->total_price)->toBe(0.0);
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

test('total_price sums multiple non-LOST items', function () {
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

    expect((float) $order->fresh()->total_price)->toBe(300.0);
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

test('total_price is 0 when all items are LOST', function () {
    $order = Order::factory()->create(['total_price' => 500]);

    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 500.00,
        'status'      => OrderItemStatus::LOST->value,
    ]);

    expect((float) $order->fresh()->total_price)->toBe(0.0);
});
