<?php

namespace App\Services\Ai\Context;

use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Database\Eloquent\Model;

/**
 * Context for one sales lead: the deal and everything hanging off it. Its own
 * orders are listed in full; earlier trajectories of the same patient are history.
 */
class SalesLeadContextBuilder extends AiContextBuilder
{
    protected function resolveScope(Model $subject): AiContextScope
    {
        /** @var SalesLead $subject */
        $subject->loadMissing(['stage', 'persons', 'orders.stage', 'department', 'user']);

        return $this->scopeForPersons(
            personIds: $this->personIdsOf($subject),
            primaryLeadId: $subject->lead_id,
            currentSalesLeadId: $subject->id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function subjectEntry(Model $subject, AiContextScope $scope): array
    {
        /** @var SalesLead $subject */
        $source = $this->salesLeadSource($subject);
        $orders = $subject->orders;

        $data = [
            'id'           => $subject->id,
            'updated_at'   => $this->date($subject->updated_at),
            'name'         => $subject->name,
            'description'  => $this->compactText($subject->description, 800),
            'stage'        => $subject->stage?->name,
            'department'   => $subject->department?->name,
            'owner'        => $subject->user?->name,
            'closed_at'    => $this->dateOnly($subject->closed_at),
            'person_count' => $subject->persons->count() > 1 ? $subject->persons->count() : null,
            'order_count'  => $orders->count(),
            'total_value'  => round((float) $orders->sum(fn (Order $order) => (float) $order->total_price), 2),
            'ref'          => $source['ref'] ?? null,
            '_source'      => $source,
        ];

        if ($subject->stage?->is_lost && $subject->lost_reason) {
            $data['lost_reason'] = $subject->lost_reason->label();
        }

        return $data;
    }

    /**
     * All orders of this deal, not just the newest one — for a sales lead the order
     * lines are the deal, so they belong in the payload in full.
     *
     * @return array<string, mixed>
     */
    protected function extraBlocks(Model $subject, AiContextScope $scope): array
    {
        /** @var SalesLead $subject */
        return [
            'orders' => $subject->orders
                ->sortByDesc(fn (Order $order) => $order->created_at?->getTimestamp() ?? 0)
                ->map(fn (Order $order) => $this->orderEntry($order, $subject))
                ->values()
                ->all(),
        ];
    }
}
