<?php

use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Enums\ResourceType as ResourceTypeEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\ResourceType;
use App\Models\SalesLead;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->orderStatusService = app(OrderStatusService::class);

    $this->plannableResourceType = ResourceType::factory()->create([
        'name' => ResourceTypeEnum::MRI_SCANNER->label(),
    ]);
});

test('order stage is voorbereiden when not all planable items are planned', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
    ]);
    $salesLead = SalesLead::factory()->create();
    $order->sales_lead_id = $salesLead->id;
    $order->save();

    $productWithPartner = Product::factory()->create();
    $productWithoutPartner = Product::factory()->create();

    PartnerProduct::factory()->create([
        'product_id'       => $productWithPartner->id,
        'resource_type_id' => $this->plannableResourceType->id,
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::PLANNED->value,
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);

    // Non-planable item (no partner products) - should be ignored
    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithoutPartner->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);

    $calculatedStageId = $this->orderStatusService->calculate($order);

    expect($calculatedStageId)->toEqual(PipelineStage::ORDER_CONFIRM->id());
});

test('order stage is wachten uitvoering when all planable items are planned', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);
    $salesLead = SalesLead::factory()->create();
    $order->sales_lead_id = $salesLead->id;
    $order->save();

    $productWithPartner = Product::factory()->create();
    $productWithoutPartner = Product::factory()->create();

    PartnerProduct::factory()->create([
        'product_id'       => $productWithPartner->id,
        'resource_type_id' => $this->plannableResourceType->id,
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::PLANNED->value,
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::PLANNED->value,
    ]);

    // Non-planable item (no partner products) - should be ignored
    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithoutPartner->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);

    $calculatedStageId = $this->orderStatusService->calculate($order);

    expect($calculatedStageId)->toEqual(PipelineStage::ORDER_INGEPLAND->id());
});

test('order stage changes to wachten uitvoering when all planable items become planned', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);
    $salesLead = SalesLead::factory()->create();
    $order->sales_lead_id = $salesLead->id;
    $order->save();

    $productWithPartner = Product::factory()->create();

    PartnerProduct::factory()->create([
        'product_id'       => $productWithPartner->id,
        'resource_type_id' => $this->plannableResourceType->id,
    ]);

    $item1 = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);

    $item2 = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);

    expect($order->fresh()->pipeline_stage_id)->toEqual(PipelineStage::ORDER_INGEPLAND->id());

    $item1->update(['status' => OrderItemStatus::PLANNED->value]);
    $item2->update(['status' => OrderItemStatus::PLANNED->value]);

    $this->orderStatusService->recalculateAndPersist($order->fresh());

    expect($order->fresh()->pipeline_stage_id)->toEqual(PipelineStage::ORDER_INGEPLAND->id());
});

test('order stage changes to voorbereiden when unplanned planable item is added', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
    ]);
    $salesLead = SalesLead::factory()->create();
    $order->sales_lead_id = $salesLead->id;
    $order->save();

    $productWithPartner = Product::factory()->create();

    PartnerProduct::factory()->create([
        'product_id'       => $productWithPartner->id,
        'resource_type_id' => $this->plannableResourceType->id,
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::PLANNED->value,
    ]);

    $this->orderStatusService->recalculateAndPersist($order->fresh());
    expect($order->fresh()->pipeline_stage_id)->toEqual(PipelineStage::ORDER_INGEPLAND->id());

    // Add a new unplanned planable item
    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);

    $this->orderStatusService->recalculateAndPersist($order->fresh());

    expect($order->fresh()->pipeline_stage_id)->toEqual(PipelineStage::ORDER_CONFIRM->id());
});

test('order stage stays wachten uitvoering when non planable item is added', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
    ]);
    $salesLead = SalesLead::factory()->create();
    $order->sales_lead_id = $salesLead->id;
    $order->save();

    $productWithPartner = Product::factory()->create();
    $productWithoutPartner = Product::factory()->create();

    PartnerProduct::factory()->create([
        'product_id'       => $productWithPartner->id,
        'resource_type_id' => $this->plannableResourceType->id,
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::PLANNED->value,
    ]);

    $this->orderStatusService->recalculateAndPersist($order->fresh());
    expect($order->fresh()->pipeline_stage_id)->toEqual(PipelineStage::ORDER_INGEPLAND->id());

    // Add a new non-planable item (should not affect stage)
    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithoutPartner->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);

    $this->orderStatusService->recalculateAndPersist($order->fresh());

    expect($order->fresh()->pipeline_stage_id)->toEqual(PipelineStage::ORDER_INGEPLAND->id());
});

test('order stage changes to voorbereiden when planned planable item becomes unplanned', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
    ]);
    $salesLead = SalesLead::factory()->create();
    $order->sales_lead_id = $salesLead->id;
    $order->save();

    $productWithPartner = Product::factory()->create();

    PartnerProduct::factory()->create([
        'product_id'       => $productWithPartner->id,
        'resource_type_id' => $this->plannableResourceType->id,
    ]);

    $item1 = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::PLANNED->value,
    ]);

    $item2 = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::PLANNED->value,
    ]);

    $this->orderStatusService->recalculateAndPersist($order->fresh());
    expect($order->fresh()->pipeline_stage_id)->toEqual(PipelineStage::ORDER_INGEPLAND->id());

    // Remove planning from one planable item
    $item1->update(['status' => OrderItemStatus::NEW->value]);

    $this->orderStatusService->recalculateAndPersist($order->fresh());

    expect($order->fresh()->pipeline_stage_id)->toEqual(PipelineStage::ORDER_CONFIRM->id());
});

test('order stage persists when recalculated', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);
    $salesLead = SalesLead::factory()->create();
    $order->sales_lead_id = $salesLead->id;
    $order->save();

    $productWithPartner = Product::factory()->create();

    PartnerProduct::factory()->create([
        'product_id'       => $productWithPartner->id,
        'resource_type_id' => $this->plannableResourceType->id,
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::PLANNED->value,
    ]);

    $this->orderStatusService->recalculateAndPersist($order->fresh());

    $this->assertDatabaseHas('orders', [
        'id'                => $order->id,
        'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
    ]);
});
