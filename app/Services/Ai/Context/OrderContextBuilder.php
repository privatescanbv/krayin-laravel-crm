<?php

namespace App\Services\Ai\Context;

use App\Models\Order;
use App\Models\OrderCheck;
use App\Models\OrderItem;
use App\Models\SalesLead;
use Illuminate\Database\Eloquent\Model;

/**
 * Context for one order: the order itself is the running thread, sibling orders
 * and earlier trajectories of the same patient become history.
 */
class OrderContextBuilder extends AiContextBuilder
{
    protected function resolveScope(Model $subject): AiContextScope
    {
        /** @var Order $subject */
        // Everything subjectEntry() and extraBlocks() touch, so the payload costs no
        // extra queries; generation builds this context twice.
        $subject->loadMissing([
            'stage',
            'user',
            'clinicCoordinator',
            'payments',
            'personConfirmations',
            'orderItems',
            'orderChecks',
            'salesLead.persons',
            'salesLead.stage',
        ]);

        $salesLead = $subject->salesLead;

        return $this->scopeForPersons(
            personIds: $salesLead instanceof SalesLead ? $this->personIdsOf($salesLead) : collect(),
            primaryLeadId: $salesLead?->lead_id,
            currentSalesLeadId: $subject->sales_lead_id,
            currentOrderId: $subject->id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function subjectEntry(Model $subject, AiContextScope $scope): array
    {
        /** @var Order $subject */
        $entry = $this->orderEntry($subject, $subject->salesLead);
        $confirmation = $subject->confirmationProgress();
        $openAmount = round((float) $subject->total_price - $subject->netReceivedAmount(), 2);

        return array_merge($entry, [
            // The title is already carried by the entry's "description".
            'updated_at'         => $this->date($subject->updated_at),
            'examination_time'   => $subject->first_examination_time,
            'closed_at'          => $this->dateOnly($subject->closed_at),
            'days_until_exam'    => $this->daysUntilExamination($subject),
            'sales_lead'         => $subject->salesLead?->name,
            'owner'              => $subject->user?->name,
            'clinic_coordinator' => $subject->clinicCoordinator?->name,
            'payment_status'     => $subject->paymentStatus()->label(),
            'open_amount'        => $openAmount > 0 ? $openAmount : null,
            'confirmations'      => $confirmation['total'] > 0
                ? $confirmation['confirmed'].' van '.$confirmation['total'].' bevestigd'
                : null,
        ]);
    }

    /**
     * The operational detail sales needs before calling a patient: what was ordered,
     * and which checks are still open.
     *
     * @return array<string, mixed>
     */
    protected function extraBlocks(Model $subject, AiContextScope $scope): array
    {
        /** @var Order $subject */
        return [
            'order_items' => $subject->displayableOrderItems()
                ->map(fn (OrderItem $item) => array_filter([
                    'name'     => $this->compactText($item->name, 120),
                    'quantity' => $item->quantity > 1 ? $item->quantity : null,
                    'value'    => (float) $item->total_price > 0 ? round((float) $item->total_price, 2) : null,
                    'status'   => $this->enumValue($item->status),
                ], fn ($value) => $value !== null && $value !== ''))
                ->values()
                ->all(),

            'open_checks' => $subject->orderChecks
                ->reject(fn (OrderCheck $check) => (bool) $check->done)
                ->map(fn (OrderCheck $check) => $this->compactText($check->name, 120))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function daysUntilExamination(Order $order): ?int
    {
        $examination = $order->first_examination_at;

        if ($examination === null || $examination->isPast()) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($examination->copy()->startOfDay());
    }
}
