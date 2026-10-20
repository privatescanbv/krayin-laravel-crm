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
     * heeft dan het te betalen bedrag na een LOST-markering (verloren order: alles terug).
     *
     * Idempotent: als er al een REFUND bestaat die het surplus dekt, is surplus 0 → geen actie.
     */
    public function createRefundIfSurplus(Order $order, ?int $userId = null): ?OrderPayment
    {
        // Verse betaal-, stage- en regeldata laden zodat eerder aangemaakte refunds en net gewijzigde statussen worden meegenomen.
        $order->unsetRelation('payments');
        $order->unsetRelation('stage');
        $order->unsetRelation('orderItems');

        $paid = $order->netPaidAmount();
        $total = $order->payableTotal();
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
            'schedule_to' => now(),
        ]);

        return $refund;
    }
}
