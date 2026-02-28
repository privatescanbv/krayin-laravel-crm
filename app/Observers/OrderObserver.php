<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\DomainEvents\DomainEventBuilder;
use App\Services\DomainEvents\RedisEventPublisher;
use App\Services\OrderStatusService;

class OrderObserver
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly RedisEventPublisher $redisEventPublisher,
    ) {}

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Don't auto-update if pipeline_stage_id was manually changed
        if ($order->wasChanged('pipeline_stage_id')) {
            $event = DomainEventBuilder::pipelineStageChanged(
                aggregateType: 'Order',
                entity: $order,
                oldStageId: $order->getOriginal('pipeline_stage_id')
                    ? (int) $order->getOriginal('pipeline_stage_id')
                    : null,
                newStageId: (int) $order->pipeline_stage_id,
            );
            $this->redisEventPublisher->publish($event);

            return;
        }

        $this->orderStatusService->recalculateAndPersist($order);
    }
}
