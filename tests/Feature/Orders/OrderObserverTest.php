<?php

use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Enums\ResourceType as ResourceTypeEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\ResourceType;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('updating to a won stage marks all non-lost order items as won', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);

    $newItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::NEW->value]);
    $plannedItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::PLANNED->value]);
    $lostItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::LOST->value]);

    $order->pipeline_stage_id = PipelineStage::ORDER_BEVESTIGD->id();
    $order->save();

    expect($newItem->fresh()->status)->toBe(OrderItemStatus::NEW)
        ->and($plannedItem->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($lostItem->fresh()->status)->toBe(OrderItemStatus::LOST);
});

test('updating to a hernia won stage marks order items as won', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id(),
    ]);

    $newItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::NEW->value]);
    $plannedItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::PLANNED->value]);
    $lostItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::LOST->value]);

    $order->pipeline_stage_id = PipelineStage::ORDER_BEVESTIGD_HERNIA->id();
    $order->save();

    expect($newItem->fresh()->status)->toBe(OrderItemStatus::NEW)
        ->and($plannedItem->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($lostItem->fresh()->status)->toBe(OrderItemStatus::LOST);
});

test('updating to a non-won stage does not change order item status', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);

    $newItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::NEW->value]);
    $plannedItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::PLANNED->value]);
    $lostItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::LOST->value]);

    $order->pipeline_stage_id = PipelineStage::ORDER_INGEPLAND->id();
    $order->save();

    expect($newItem->fresh()->status)->toBe(OrderItemStatus::NEW);
    expect($plannedItem->fresh()->status)->toBe(OrderItemStatus::PLANNED);
    expect($lostItem->fresh()->status)->toBe(OrderItemStatus::LOST);
});

test('order items from other orders are not affected when one order moves to won stage', function () {
    $orderA = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);
    $orderB = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);

    $itemA = OrderItem::factory()->create(['order_id' => $orderA->id, 'status' => OrderItemStatus::PLANNED->value]);
    $itemB = OrderItem::factory()->create(['order_id' => $orderB->id, 'status' => OrderItemStatus::NEW->value]);

    $orderA->pipeline_stage_id = PipelineStage::ORDER_BEVESTIGD->id();
    $orderA->save();

    expect($itemA->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($itemB->fresh()->status)->toBe(OrderItemStatus::NEW);
});

test('created dispatches order.update_stage.after event', function () {
    Event::fake(['order.update_stage.after']);

    Order::factory()->create();

    Event::assertDispatched('order.update_stage.after');
});

test('updating to a won stage dispatches order.update_stage.after event', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);

    Event::fake(['order.update_stage.after']);

    $order->pipeline_stage_id = PipelineStage::ORDER_BEVESTIGD->id();
    $order->save();

    Event::assertDispatched('order.update_stage.after');
});

test('touching order does not downgrade from order akkoord when plannable items are WON', function () {
    $plannableResourceType = ResourceType::where('name', ResourceTypeEnum::MRI_SCANNER->label())->firstOrFail();
    $salesLead = SalesLead::factory()->create();
    $productWithPartner = Product::factory()->create();
    PartnerProduct::factory()->create([
        'product_id'       => $productWithPartner->id,
        'resource_type_id' => $plannableResourceType->id,
    ]);

    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_BEVESTIGD->id(),
        'sales_lead_id'     => $salesLead->id,
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::WON->value,
    ]);

    $order->touch();

    expect($order->fresh()->pipeline_stage_id)->toBe(PipelineStage::ORDER_BEVESTIGD->id());
});
