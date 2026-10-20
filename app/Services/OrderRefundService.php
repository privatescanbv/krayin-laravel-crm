<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\OrderPayment;
use Webkul\Activity\Repositories\ActivityRepository;

class OrderRefundService
{
    public const TASK_TITLE = 'Klant terugbetalen';

    public function __construct(
        private readonly ActivityRepository $activityRepository,
    ) {}

    /**
     * Maak een REFUND-betaling en bijbehorende taakactiviteit aan als de klant meer betaald
     * heeft dan het te betalen bedrag na een LOST-markering (verloren order: alles terug).
     *
     * Idempotent: als er al een REFUND bestaat die het surplus dekt, is surplus 0 → geen actie.
     * Omgekeerd: is er meer openstaand terug te betalen dan de klant te veel betaald heeft (bijv. een
     * regel toegevoegd ná een automatische terugbetaling), dan worden openstaande automatische refunds
     * verlaagd/verwijderd. Handmatig ingevoerde refunds worden nooit aangepast.
     */
    public function createRefundIfSurplus(Order $order, ?int $userId = null): ?OrderPayment
    {
        $surplus = $this->surplus($order);

        if ($surplus <= 0.01) {
            $this->reconcilePendingRefunds($order);

            return null;
        }

        // Betaalmethode overnemen van de laatste niet-refund betaling; fallback BANK.
        $latest = $order->payments()
            ->where('type', '!=', PaymentType::REFUND->value)
            ->latest('id')
            ->first();

        $refund = OrderPayment::create([
            'order_id'     => $order->id,
            'amount'       => $surplus,
            'type'         => PaymentType::REFUND,
            'method'       => $latest?->method ?? PaymentMethod::BANK,
            'paid_at'      => null,
            'currency'     => $latest?->currency ?? 'EUR',
            'auto_created' => true,
            'created_by'   => $userId,
        ]);

        $this->activityRepository->create([
            'type'        => ActivityType::TASK,
            'title'       => self::TASK_TITLE,
            'comment'     => 'Automatisch aangemaakt: order item op verloren gezet terwijl klant al € '.number_format($surplus, 2, ',', '.').' had betaald.',
            'is_done'     => false,
            'user_id'     => $userId,
            'order_id'    => $order->id,
            'schedule_to' => now(),
        ]);

        return $refund;
    }

    /**
     * Rond de open "Klant terugbetalen" taken af zodra er niets meer terug te betalen is: geen openstaande
     * (niet uitbetaalde) terugbetaling meer, of de klant heeft niet meer betaald dan het te betalen bedrag
     * (bijv. product van hetzelfde bedrag toegevoegd). Legt dat vast in het wijzigingslogboek.
     * Aangeroepen door OrderPaymentObserver en createRefundIfSurplus().
     */
    public function closeRefundTasksIfSettled(Order $order): void
    {
        $order->unsetRelation('payments');
        $order->unsetRelation('stage');
        $order->unsetRelation('orderItems');

        $hasPendingRefund = $order->payments->contains(fn (OrderPayment $p) => $p->type === PaymentType::REFUND && $p->paid_at === null);

        if ($hasPendingRefund && $order->paymentStatus() === OrderPaymentStatus::CREDIT) {
            return;
        }

        $tasks = $order->activities()
            ->where('type', ActivityType::TASK->value)
            ->where('title', self::TASK_TITLE)
            ->where('is_done', false)
            ->get();

        foreach ($tasks as $task) {
            $task->update(['is_done' => true]);

            $this->activityRepository->createSystem([
                'title'      => sprintf("Taak '%s' automatisch afgerond", self::TASK_TITLE),
                'comment'    => 'Niets meer terug te betalen aan de klant (terugbetaling uitbetaald, vervallen of verwijderd, of het te betalen bedrag is gestegen).',
                'user_id'    => auth()->guard('user')->id(),
                'order_id'   => $order->id,
                'additional' => [
                    'old'         => ['label' => 'Open'],
                    'new'         => ['label' => 'Afgerond'],
                    'activity_id' => $task->id,
                ],
            ]);
        }
    }

    /**
     * Verlaag openstaande automatische refunds tot wat de klant echt te veel betaald heeft en rond
     * de "Klant terugbetalen" taken af als er niets meer terug te betalen is. Maakt nooit een nieuwe refund aan.
     */
    public function reconcilePendingRefunds(Order $order): void
    {
        $surplus = $this->surplus($order);

        if ($surplus < -0.01) {
            $this->shrinkPendingRefunds($order, -$surplus);
        }

        $this->closeRefundTasksIfSettled($order);
    }

    /**
     * Verlaag openstaande (niet uitbetaalde) automatische refunds, nieuwste eerst, met maximaal $excess.
     * Elke wijziging komt in het wijzigingslogboek.
     */
    private function shrinkPendingRefunds(Order $order, float $excess): void
    {
        $pending = $order->payments()
            ->where('type', PaymentType::REFUND->value)
            ->whereNull('paid_at')
            ->where('auto_created', true)
            ->latest('id')
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        foreach ($pending as $refund) {
            if ($excess <= 0.01) {
                break;
            }

            $amount = (float) $refund->amount;
            $newAmount = $amount <= $excess + 0.01 ? 0.0 : round($amount - $excess, 2);
            $excess = round($excess - ($amount - $newAmount), 2);

            $this->activityRepository->createSystem([
                'title'      => $newAmount > 0
                    ? sprintf('Automatische terugbetaling verlaagd: %s → %s', $this->money($amount), $this->money($newAmount))
                    : sprintf('Automatische terugbetaling van %s vervallen', $this->money($amount)),
                'comment'    => 'Het te betalen bedrag van de order is gestegen, waardoor de klant minder te veel betaald heeft dan eerder berekend.',
                'user_id'    => auth()->guard('user')->id(),
                'order_id'   => $order->id,
                'additional' => [
                    'old'        => ['label' => $this->money($amount)],
                    'new'        => ['label' => $newAmount > 0 ? $this->money($newAmount) : 'Vervallen'],
                    'payment_id' => $refund->id,
                ],
            ]);

            // Logged first so the task closed by OrderPaymentObserver appears after this entry.
            $newAmount > 0 ? $refund->update(['amount' => $newAmount]) : $refund->delete();
        }
    }

    /**
     * Netto betaald (incl. openstaande refunds) minus te betalen, op verse betaal-, stage- en regeldata.
     */
    private function surplus(Order $order): float
    {
        $order->unsetRelation('payments');
        $order->unsetRelation('stage');
        $order->unsetRelation('orderItems');

        return round($order->netPaidAmount() - $order->payableTotal(), 2);
    }

    private function money(float $amount): string
    {
        return '€ '.number_format($amount, 2, ',', '.');
    }
}
