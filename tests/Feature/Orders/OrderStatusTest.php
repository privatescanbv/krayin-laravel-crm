<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Webkul\Product\Models\Product;

class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    protected OrderStatusService $orderStatusService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderStatusService = app(OrderStatusService::class);
    }

    /**
     * Test that order status is NEW when no order items exist
     */
    public function test_order_status_is_new_when_no_items_exist(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PLANNED]);

        $calculatedStatus = $this->orderStatusService->calculate($order);

        $this->assertEquals(OrderStatus::NEW, $calculatedStatus);
    }

    /**
     * Test that order status is NEW when not all planable items are planned
     */
    public function test_order_status_is_new_when_not_all_planable_items_are_planned(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PLANNED]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();
        $productWithoutPartner = Product::factory()->create();

        // Create partner product for first product
        PartnerProduct::factory()->create(['product_id' => $productWithPartner->id]);

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

        $calculatedStatus = $this->orderStatusService->calculate($order);

        $this->assertEquals(OrderStatus::NEW, $calculatedStatus);
    }

    /**
     * Test that order status is PLANNED when all planable items are planned
     */
    public function test_order_status_is_planned_when_all_planable_items_are_planned(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::NEW]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();
        $productWithoutPartner = Product::factory()->create();

        // Create partner products for first product
        PartnerProduct::factory()->create(['product_id' => $productWithPartner->id]);

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

        $calculatedStatus = $this->orderStatusService->calculate($order);

        $this->assertEquals(OrderStatus::PLANNED, $calculatedStatus);
    }

    /**
     * Test that order status automatically changes to PLANNED when all planable items become planned
     */
    public function test_order_status_changes_to_planned_when_all_planable_items_become_planned(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::NEW]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();

        // Create partner products
        PartnerProduct::factory()->create(['product_id' => $productWithPartner->id]);

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

        // Verify order is NEW
        $this->assertEquals(OrderStatus::NEW, $order->fresh()->status);

        // Plan all planable items
        $item1->update(['status' => OrderItemStatus::PLANNED->value]);
        $item2->update(['status' => OrderItemStatus::PLANNED->value]);

        // Recalculate order status
        $this->orderStatusService->recalculateAndPersist($order->fresh());

        // Verify order status changed to PLANNED
        $this->assertEquals(OrderStatus::PLANNED, $order->fresh()->status);
    }

    /**
     * Test that order status changes back to NEW when a new unplanned planable item is added
     */
    public function test_order_status_changes_to_new_when_unplanned_planable_item_is_added(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PLANNED]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();

        // Create partner products
        PartnerProduct::factory()->create(['product_id' => $productWithPartner->id]);

        // Create order items - all planned
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $productWithPartner->id,
            'status'     => OrderItemStatus::PLANNED->value,
        ]);

        // Verify order is PLANNED
        $this->orderStatusService->recalculateAndPersist($order->fresh());
        $this->assertEquals(OrderStatus::PLANNED, $order->fresh()->status);

        // Add a new unplanned planable item
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $productWithPartner->id,
            'status'     => OrderItemStatus::NEW->value,
        ]);

        // Recalculate order status
        $this->orderStatusService->recalculateAndPersist($order->fresh());

        // Verify order status changed back to NEW
        $this->assertEquals(OrderStatus::NEW, $order->fresh()->status);
    }

    /**
     * Test that order status stays PLANNED when a non-planable item is added
     */
    public function test_order_status_stays_planned_when_non_planable_item_is_added(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PLANNED]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();
        $productWithoutPartner = Product::factory()->create();

        // Create partner products for first product
        PartnerProduct::factory()->create(['product_id' => $productWithPartner->id]);

        // Create order items - all planable items planned
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $productWithPartner->id,
            'status'     => OrderItemStatus::PLANNED->value,
        ]);

        // Verify order is PLANNED
        $this->orderStatusService->recalculateAndPersist($order->fresh());
        $this->assertEquals(OrderStatus::PLANNED, $order->fresh()->status);

        // Add a new non-planable item (should not affect status)
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $productWithoutPartner->id,
            'status'     => OrderItemStatus::NEW->value,
        ]);

        // Recalculate order status
        $this->orderStatusService->recalculateAndPersist($order->fresh());

        // Verify order status stays PLANNED
        $this->assertEquals(OrderStatus::PLANNED, $order->fresh()->status);
    }

    /**
     * Test that order status changes back to NEW when a planned planable item becomes unplanned
     */
    public function test_order_status_changes_to_new_when_planned_planable_item_becomes_unplanned(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PLANNED]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();

        // Create partner products
        PartnerProduct::factory()->create(['product_id' => $productWithPartner->id]);

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

        // Verify order is PLANNED
        $this->orderStatusService->recalculateAndPersist($order->fresh());
        $this->assertEquals(OrderStatus::PLANNED, $order->fresh()->status);

        // Remove planning from one planable item (simulate ResourceOrderItem deletion)
        $item1->update(['status' => OrderItemStatus::NEW->value]);

        // Recalculate order status
        $this->orderStatusService->recalculateAndPersist($order->fresh());

        // Verify order status changed back to NEW
        $this->assertEquals(OrderStatus::NEW, $order->fresh()->status);
    }

    /**
     * Test that order status persists correctly when recalculated
     */
    public function test_order_status_persists_when_recalculated(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::NEW]);
        $salesLead = SalesLead::factory()->create();
        $order->sales_lead_id = $salesLead->id;
        $order->save();

        $productWithPartner = Product::factory()->create();

        // Create partner products
        PartnerProduct::factory()->create(['product_id' => $productWithPartner->id]);

        // Create order items - all planable items planned
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $productWithPartner->id,
            'status'     => OrderItemStatus::PLANNED->value,
        ]);

        // Recalculate and persist
        $this->orderStatusService->recalculateAndPersist($order->fresh());

        // Verify status was persisted in database
        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatus::PLANNED->value,
        ]);
    }
}
