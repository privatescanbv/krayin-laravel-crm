<?php

namespace App\Services\Mail;

use App\Models\Order;
use Webkul\Email\Models\Email;
use Webkul\Email\Repositories\EmailRepository;

/**
 * Links an outbound email to an order by setting {@see Email::$order_id},
 * so the mail appears on the order activiteiten tab via the ConcatsEmailActivities merge.
 */
class OrderConfirmationEmailActivityService
{
    public function __construct(
        private readonly EmailRepository $emailRepository,
    ) {}

    public function linkEmailToOrderIfValid(Email $email, ?int $orderId): void
    {
        if ($orderId === null || $orderId <= 0) {
            return;
        }

        if (! $email->sales_lead_id) {
            return;
        }

        $order = Order::query()
            ->whereKey($orderId)
            ->where('sales_lead_id', $email->sales_lead_id)
            ->first();

        if (! $order) {
            return;
        }

        $this->emailRepository->update([
            'order_id' => $order->id,
        ], $email->id);
    }
}
