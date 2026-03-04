<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesLead;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    /**
     * Calculate the correct pipeline stage ID for an Order.
     *
     * Rules:
     * - Only order items that can be planned (have partner products) are considered
     * - If all planable order items are PLANNED → stage = order-wachten-uitvoering
     * - If any planable order item is not PLANNED → stage = order-voorbereiden
     * - If no planable order items exist → stage = order-voorbereiden
     */
    public function calculate(Order $order): int
    {
        $orderItems = OrderItem::query()
            ->where('order_id', $order->id)
            ->select('order_items.id', 'order_items.status', 'order_items.product_id')
            ->selectRaw('COUNT(partner_products.id) AS partner_product_count')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('partner_products', 'products.id', '=', 'partner_products.product_id')
            ->groupBy('order_items.id', 'order_items.status', 'order_items.product_id')
            ->get();

        // Determine department to pick correct pipeline stages
        $isHernia = SalesLead::isHerniaPoli($order->sales_lead_id);

        $firstStageId = $isHernia
            ? PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id()
            : PipelineStage::ORDER_CONFIRM->id();

        $plannedStageId = $isHernia
            ? PipelineStage::ORDER_INGEPLAND_HERNIA->id()
            : PipelineStage::ORDER_INGEPLAND->id();

        if ($orderItems->isEmpty()) {
            return $firstStageId;
        }

        // Filter to only planable items (items with partner products)
        $planableItems = $orderItems->filter(function (OrderItem $item) {
            return $item->isPlannable();
        });

        // If there are no planable items, order stays at first stage
        if ($planableItems->isEmpty()) {
            return $firstStageId;
        }

        // Check if all planable items are planned
        foreach ($planableItems as $orderItem) {
            if ($orderItem->status !== OrderItemStatus::PLANNED) {
                return $firstStageId;
            }
        }

        // All planable items are planned
        return $plannedStageId;
    }

    /**
     * Recalculate and persist the stage if different.
     */
    public function recalculateAndPersist(Order $order): void
    {
        $newStageId = $this->calculate($order);

        if ($order->pipeline_stage_id !== $newStageId) {
            DB::table('orders')
                ->where('id', $order->id)
                ->update(['pipeline_stage_id' => $newStageId, 'updated_at' => now()]);
        }
    }

    /**
     * Convenience by id.
     */
    public function recalculateAndPersistById(int $orderId): void
    {
        $order = new Order;
        $order->id = $orderId;
        // Fetch current pipeline_stage_id minimally
        $row = DB::table('orders')->where('id', $orderId)->first(['pipeline_stage_id', 'sales_lead_id']);
        if ($row) {
            $order->pipeline_stage_id = $row->pipeline_stage_id;
            $order->sales_lead_id = $row->sales_lead_id;
        }

        $this->recalculateAndPersist($order);
    }
}
