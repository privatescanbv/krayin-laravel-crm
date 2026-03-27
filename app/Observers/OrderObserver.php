<?php

namespace App\Observers;

use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderNumberGenerator;
use App\Services\OrderStatusService;
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
        if ($stageChange && in_array($order->pipeline_stage_id, PipelineStage::getOrderStagesIdsWon(), true)) {
            Log::info('Updating order items to WON for order '.$order->id);
            OrderItem::forOrderAndNotLostAndNew($order->id)
                ->update(['status' => OrderItemStatus::WON->value]);
        }
    }
}
