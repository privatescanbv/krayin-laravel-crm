<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\OrderStatusService;

class OrderObserver
{
    public function __construct(private readonly OrderStatusService $orderStatusService) {}

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Don't auto-update if status was manually changed
        if ($order->wasChanged('status')) {
            return;
        }

        $this->orderStatusService->recalculateAndPersist($order);
    }
}
