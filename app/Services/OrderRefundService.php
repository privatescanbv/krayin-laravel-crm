<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\OrderPayment;
use Webkul\Activity\Repositories\ActivityRepository;

class OrderRefundService
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
    ) {}

    /**
     * Maak een REFUND-betaling en bijbehorende taakactiviteit aan als de klant meer betaald
     * heeft dan de huidige order total na een LOST-markering.
     *
     * Idempotent: als er al een REFUND bestaat die het surplus dekt, is surplus 0 → geen actie.
     */
    public function createRefundIfSurplus(Order $order, ?int $userId = null): ?OrderPayment
    {
        // Verse betaaldata laden zodat eerder aangemaakte refunds worden meegenomen.
        $order->unsetRelation('payments');

        $paid = $order->netPaidAmount();
        $total = round((float) $order->total_price, 2);
        $surplus = round($paid - $total, 2);

        if ($surplus <= 0.01) {
            return null;
        }

        // Betaalmethode overnemen van de laatste niet-refund betaling; fallback BANK.
        $latest = $order->payments()
            ->where('type', '!=', PaymentType::REFUND->value)
            ->latest('id')
            ->first();

        $refund = OrderPayment::create([
            'order_id'   => $order->id,
            'amount'     => $surplus,
            'type'       => PaymentType::REFUND,
            'method'     => $latest?->method ?? PaymentMethod::BANK,
            'paid_at'    => null,
            'currency'   => $latest?->currency ?? 'EUR',
            'created_by' => $userId,
        ]);

        $this->activityRepository->create([
            'type'        => ActivityType::TASK,
            'title'       => 'Klant terugbetalen',
            'comment'     => 'Automatisch aangemaakt: order item op verloren gezet terwijl klant al € '.number_format($surplus, 2, ',', '.').' had betaald.',
            'is_done'     => false,
            'user_id'     => $userId,
            'order_id'    => $order->id,
            'schedule_to' => now()->addWeek(),
        ]);

        return $refund;
    }
}
