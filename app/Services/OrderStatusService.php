<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesLead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class OrderStatusService
{
    /**
     * Calculate the correct pipeline stage ID for an Order, before or in planning stages, based on the status of its order items.
     *
     * Rules:
     * - Only order items that can be planned (have partner products) are considered
     * - If all planable order items are PLANNED → stage = order-wachten-uitvoering
     * - If any planable order item is not PLANNED → stage = order-voorbereiden
     * - If no planable order items exist → stage = order-voorbereiden
     */
    public function calculate(Order $order): int
    {

        $orderItems = OrderItem::withPartnerProductCount()
            ->where('order_id', $order->id)
            ->get();

        // Determine department to pick correct pipeline stages
        $isHernia = SalesLead::isHerniaPoli($order->sales_lead_id);

        $firstStageId = $isHernia
            ? PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id()
            : PipelineStage::ORDER_CONFIRM->id();

        $plannedStageId = $isHernia
            ? PipelineStage::ORDER_INGEPLAND_HERNIA->id()
            : PipelineStage::ORDER_INGEPLAND->id();

        if (is_null($order->pipeline_stage_id)) {
            return $firstStageId;
        }
        // Filter to only plannable items (items with partner products)
        $plannableItems = $orderItems->filter(function (OrderItem $item) {
            return $item->isPlannable();
        });

        // Check if all plannable items are planned
        foreach ($plannableItems as $orderItem) {
            if ($orderItem->status !== OrderItemStatus::PLANNED) {
                Log::info("Order {$order->id} has unplanned items, setting stage to {$firstStageId}");
                return $firstStageId;
            }
        }

        // All plannable items are planned
        // only auto recalculate if order is before confirmed stage, otherwise keep current stage
        if (in_array($order->pipeline_stage_id, PipelineStage::getOrderStagesIdsBeforePlanned())) {
            // change stage to planned
            Log::info('All plannable items for order '.$order->id.' are planned, setting stage to '.$plannedStageId);
            return $plannedStageId;
        }

        // keep current stage
        return $order->pipeline_stage_id;
    }

    /**
     * Recalculate and persist the stage if different.
     */
    public function recalculateAndPersist(Order $order): ?string
    {
        $newStageId = $this->calculate($order);

        if ($order->pipeline_stage_id !== $newStageId) {
            DB::table('orders')
                ->where('id', $order->id)
                ->update(['pipeline_stage_id' => $newStageId, 'updated_at' => now()]);

            $order->pipeline_stage_id = $newStageId;
            Event::dispatch('order.update_stage.after', $order);

            return $newStageId;
        }

        return null;
    }

    /**
     * Convenience by id.
     */
    public function recalculateAndPersistById(int $orderId): void
    {
        $order = Order::find($orderId);
        if ($order) {
            $this->recalculateAndPersist($order);
        }
    }
}
