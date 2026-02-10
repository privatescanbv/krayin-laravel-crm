<?php

namespace Tests\Feature\Orders;

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
use Tests\TestCase;
use Webkul\Product\Models\Product;

class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    protected OrderStatusService $orderStatusService;

    protected ResourceType $plannableResourceType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderStatusService = app(OrderStatusService::class);

        // Create a valid plannable resource type
        $this->plannableResourceType = ResourceType::factory()->create([
            'name' => ResourceTypeEnum::MRI_SCANNER->label(),
        ]);
    }

    /**
     * Test that order stage is "voorbereiden" when no order items exist
     */
    public function test_order_stage_is_voorbereiden_when_no_items_exist(): void
    {
        $order = Order::factory()->create([
            'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
        ]);

        $calculatedStageId = $this->orderStatusService->calculate($order);

        $this->assertEquals(PipelineStage::ORDER_VOORBEREIDEN->id(), $calculatedStageId);
    }

    /**
     * Test that order stage is "voorbereiden" when not all planable items are planned
     */
    public function test_order_stage_is_voorbereiden_when_not_all_planable_items_are_planned(): void
    {
        $order = Order::factory()->create([
            'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
        ]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();
        $productWithoutPartner = Product::factory()->create();

        // Create partner product for first product
        PartnerProduct::factory()->create([
            'product_id'       => $productWithPartner->id,
            'resource_type_id' => $this->plannableResourceType->id,
        ]);

        // Create order items - some planned, some not, some not planable
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

        $this->assertEquals(PipelineStage::ORDER_VOORBEREIDEN->id(), $calculatedStageId);
    }

    /**
     * Test that order stage is "wachten-uitvoering" when all planable items are planned
     */
    public function test_order_stage_is_wachten_uitvoering_when_all_planable_items_are_planned(): void
    {
        $order = Order::factory()->create([
            'pipeline_stage_id' => PipelineStage::ORDER_VOORBEREIDEN->id(),
        ]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();
        $productWithoutPartner = Product::factory()->create();

        // Create partner products for first product
        PartnerProduct::factory()->create([
            'product_id'       => $productWithPartner->id,
            'resource_type_id' => $this->plannableResourceType->id,
        ]);

        // Create order items - all planable items planned
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

        $this->assertEquals(PipelineStage::ORDER_INGEPLAND->id(), $calculatedStageId);
    }

    /**
     * Test that order stage automatically changes to "wachten-uitvoering" when all planable items become planned
     */
    public function test_order_stage_changes_to_wachten_uitvoering_when_all_planable_items_become_planned(): void
    {
        $order = Order::factory()->create([
            'pipeline_stage_id' => PipelineStage::ORDER_VOORBEREIDEN->id(),
        ]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();

        // Create partner products
        PartnerProduct::factory()->create([
            'product_id'       => $productWithPartner->id,
            'resource_type_id' => $this->plannableResourceType->id,
        ]);

        // Create order items - all new
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

        // Verify order is at voorbereiden stage
        $this->assertEquals(PipelineStage::ORDER_VOORBEREIDEN->id(), $order->fresh()->pipeline_stage_id);

        // Plan all planable items
        $item1->update(['status' => OrderItemStatus::PLANNED->value]);
        $item2->update(['status' => OrderItemStatus::PLANNED->value]);

        // Recalculate order stage
        $this->orderStatusService->recalculateAndPersist($order->fresh());

        // Verify order stage changed to wachten-uitvoering
        $this->assertEquals(PipelineStage::ORDER_INGEPLAND->id(), $order->fresh()->pipeline_stage_id);
    }

    /**
     * Test that order stage changes back to "voorbereiden" when a new unplanned planable item is added
     */
    public function test_order_stage_changes_to_voorbereiden_when_unplanned_planable_item_is_added(): void
    {
        $order = Order::factory()->create([
            'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
        ]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();

        // Create partner products
        PartnerProduct::factory()->create([
            'product_id'       => $productWithPartner->id,
            'resource_type_id' => $this->plannableResourceType->id,
        ]);

        // Create order items - all planned
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $productWithPartner->id,
            'status'     => OrderItemStatus::PLANNED->value,
        ]);

        // Verify order is at wachten-uitvoering
        $this->orderStatusService->recalculateAndPersist($order->fresh());
        $this->assertEquals(PipelineStage::ORDER_INGEPLAND->id(), $order->fresh()->pipeline_stage_id);

        // Add a new unplanned planable item
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $productWithPartner->id,
            'status'     => OrderItemStatus::NEW->value,
        ]);

        // Recalculate order stage
        $this->orderStatusService->recalculateAndPersist($order->fresh());

        // Verify order stage changed back to voorbereiden
        $this->assertEquals(PipelineStage::ORDER_VOORBEREIDEN->id(), $order->fresh()->pipeline_stage_id);
    }

    /**
     * Test that order stage stays at "ORDER_INGEPLAND" when a non-planable item is added
     */
    public function test_order_stage_stays_wachten_uitvoering_when_non_planable_item_is_added(): void
    {
        $order = Order::factory()->create([
            'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
        ]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();
        $productWithoutPartner = Product::factory()->create();

        // Create partner products for first product
        PartnerProduct::factory()->create([
            'product_id'       => $productWithPartner->id,
            'resource_type_id' => $this->plannableResourceType->id,
        ]);

        // Create order items - all planable items planned
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $productWithPartner->id,
            'status'     => OrderItemStatus::PLANNED->value,
        ]);

        // Verify order is at wachten-uitvoering
        $this->orderStatusService->recalculateAndPersist($order->fresh());
        $this->assertEquals(PipelineStage::ORDER_INGEPLAND->id(), $order->fresh()->pipeline_stage_id);

        // Add a new non-planable item (should not affect stage)
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $productWithoutPartner->id,
            'status'     => OrderItemStatus::NEW->value,
        ]);

        // Recalculate order stage
        $this->orderStatusService->recalculateAndPersist($order->fresh());

        // Verify order stage stays at wachten-uitvoering
        $this->assertEquals(PipelineStage::ORDER_INGEPLAND->id(), $order->fresh()->pipeline_stage_id);
    }

    /**
     * Test that order stage changes back to "voorbereiden" when a planned planable item becomes unplanned
     */
    public function test_order_stage_changes_to_voorbereiden_when_planned_planable_item_becomes_unplanned(): void
    {
        $order = Order::factory()->create([
            'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
        ]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();

        // Create partner products
        PartnerProduct::factory()->create([
            'product_id'       => $productWithPartner->id,
            'resource_type_id' => $this->plannableResourceType->id,
        ]);

        // Create order items - all planned
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

        // Verify order is at wachten-uitvoering
        $this->orderStatusService->recalculateAndPersist($order->fresh());
        $this->assertEquals(PipelineStage::ORDER_INGEPLAND->id(), $order->fresh()->pipeline_stage_id);

        // Remove planning from one planable item (simulate ResourceOrderItem deletion)
        $item1->update(['status' => OrderItemStatus::NEW->value]);

        // Recalculate order stage
        $this->orderStatusService->recalculateAndPersist($order->fresh());

        // Verify order stage changed back to voorbereiden
        $this->assertEquals(PipelineStage::ORDER_VOORBEREIDEN->id(), $order->fresh()->pipeline_stage_id);
    }

    /**
     * Test that order stage persists correctly when recalculated
     */
    public function test_order_stage_persists_when_recalculated(): void
    {
        $order = Order::factory()->create([
            'pipeline_stage_id' => PipelineStage::ORDER_VOORBEREIDEN->id(),
        ]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();

        // Create partner products
        PartnerProduct::factory()->create([
            'product_id'       => $productWithPartner->id,
            'resource_type_id' => $this->plannableResourceType->id,
        ]);

        // Create order items - all planable items planned
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $productWithPartner->id,
            'status'     => OrderItemStatus::PLANNED->value,
        ]);

        // Recalculate and persist
        $this->orderStatusService->recalculateAndPersist($order->fresh());

        // Verify stage was persisted in database
        $this->assertDatabaseHas('orders', [
            'id'                => $order->id,
            'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
        ]);
    }
}
