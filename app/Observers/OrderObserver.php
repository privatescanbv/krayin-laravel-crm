<?php

namespace App\Observers;

use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderNumberGenerator;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly OrderNumberGenerator $orderNumberGenerator,
    ) {}

    public function creating(Order $order): void
    {
        if (! empty($order->order_number)) {
            return;
        }

        $order->order_number = $this->orderNumberGenerator->next();
    }

    public function created(Order $order): void
    {
        Event::dispatch('order.update_stage.after', $order);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        if ($order->wasChanged('pipeline_stage_id')) {
            Event::dispatch('order.update_stage.after', $order);
            $stageChange = true;
        } else {
            $newStageId = $this->orderStatusService->recalculateAndPersist($order);
            $stageChange = ! is_null($newStageId);
        }
        if ($stageChange && in_array($order->pipeline_stage_id, PipelineStage::getStageIdsAfterExecutionExLost(), true)) {
            Log::info('Updating order items to WON for order '.$order->id);
            OrderItem::forOrderAndNotLost($order->id)
                ->update(['status' => OrderItemStatus::WON->value]);
        }

        if ($stageChange && in_array($order->pipeline_stage_id, PipelineStage::getLostOrderStageIds(), true)) {
            Log::info('Updating order items to LOST for order '.$order->id);
            $orderItemIds = OrderItem::where('order_id', $order->id)->pluck('id');
            OrderItem::where('order_id', $order->id)
                ->update(['status' => OrderItemStatus::LOST->value]);
            DB::table('resource_orderitem')->whereIn('orderitem_id', $orderItemIds)->delete();
        }
    }
}
