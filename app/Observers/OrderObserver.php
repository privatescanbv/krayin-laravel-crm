<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\Event;

class OrderObserver
{
    public function __construct(private readonly OrderStatusService $orderStatusService) {}

    public function created(Order $order): void
    {
        Event::dispatch('order.update_stage.after', $order);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        if ($order->isDirty('pipeline_stage_id')) {
            Event::dispatch('order.update_stage.after', $order);
        }
        // Don't auto-update if pipeline_stage_id was manually changed
        if ($order->wasChanged('pipeline_stage_id')) {
            return;
        }

        $this->orderStatusService->recalculateAndPersist($order);
    }
}
