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
     * - Plannable items still awaiting a booking have status NEW; PLANNED/WON/LOST are treated as resolved for planning
     * - If any plannable order item is still NEW → stage = order-bevestigen (or hernia voorbereiden)
     * - If all plannable items are resolved and order is before planned stage → stage = ingepland
     * - If no plannable order items exist → keep current stage
     */
    public function calculate(Order $order): int
    {

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

        $orderItems = OrderItem::withPartnerProductCount()
            ->where('order_id', $order->id)
            ->get();
        if ($orderItems->isEmpty()) {
            // auto change stage when there are no order items
            return $order->pipeline_stage_id;
        }
        // Filter to only plannable items (items with partner products)
        $plannableItems = $orderItems->filter(function (OrderItem $item) {
            return $item->isPlannable();
        });

        // Check if any plannable item still needs a booking (only NEW counts as unplanned)
        foreach ($plannableItems as $orderItem) {
            if ($orderItem->status === OrderItemStatus::NEW) {
                Log::info("Order {$order->id} has plannable items still NEW, setting stage to {$firstStageId}");

                return $firstStageId;
            }
        }

        // All plannable items are planned or past planning (WON/LOST)
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
