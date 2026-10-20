<?php

namespace App\Observers;

use App\Enums\PaymentType;
use App\Models\OrderPayment;
use App\Services\OrderRefundService;

class OrderPaymentObserver
{
    public function __construct(
        private readonly OrderRefundService $orderRefundService,
    ) {}

    public function saved(OrderPayment $payment): void
    {
        $this->closeRefundTasks($payment);
    }

    public function deleted(OrderPayment $payment): void
    {
        $this->closeRefundTasks($payment);
    }

    private function closeRefundTasks(OrderPayment $payment): void
    {
        if ($payment->type === PaymentType::REFUND && $payment->order) {
            $this->orderRefundService->closeRefundTasksIfSettled($payment->order);
        }
    }
}
