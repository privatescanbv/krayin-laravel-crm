<?php

use App\Enums\OrderItemStatus;
use App\Models\OrderItem;
use App\Models\ResourceOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('setting status to lost deletes all resource order items', function () {
    $orderItem = OrderItem::factory()->create(['status' => OrderItemStatus::PLANNED->value]);
    ResourceOrderItem::factory()->count(2)->create(['orderitem_id' => $orderItem->id]);

    expect(ResourceOrderItem::where('orderitem_id', $orderItem->id)->count())->toBe(2);

    $orderItem->status = OrderItemStatus::LOST->value;
    $orderItem->save();

    expect(ResourceOrderItem::where('orderitem_id', $orderItem->id)->count())->toBe(0);
});

test('setting status to lost keeps status lost not reset to new', function () {
    $orderItem = OrderItem::factory()->create(['status' => OrderItemStatus::PLANNED->value]);
    ResourceOrderItem::factory()->create(['orderitem_id' => $orderItem->id]);

    $orderItem->status = OrderItemStatus::LOST->value;
    $orderItem->save();

    expect($orderItem->fresh()->status)->toBe(OrderItemStatus::LOST);
});

test('setting status to new does not delete resource order items', function () {
    $orderItem = OrderItem::factory()->create(['status' => OrderItemStatus::PLANNED->value]);
    $roi = ResourceOrderItem::factory()->create(['orderitem_id' => $orderItem->id]);

    $orderItem->status = OrderItemStatus::NEW->value;
    $orderItem->save();

    expect(ResourceOrderItem::find($roi->id))->not->toBeNull();
});

test('resource order items from other order items are not affected', function () {
    $itemA = OrderItem::factory()->create(['status' => OrderItemStatus::PLANNED->value]);
    $itemB = OrderItem::factory()->create(['status' => OrderItemStatus::PLANNED->value]);

    ResourceOrderItem::factory()->create(['orderitem_id' => $itemA->id]);
    $roiB = ResourceOrderItem::factory()->create(['orderitem_id' => $itemB->id]);

    $itemA->status = OrderItemStatus::LOST->value;
    $itemA->save();

    expect(ResourceOrderItem::find($roiB->id))->not->toBeNull();
});
