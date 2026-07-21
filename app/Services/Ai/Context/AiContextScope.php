<?php

namespace App\Services\Ai\Context;

use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Support\Collection;
use Webkul\Lead\Models\Lead;

/**
 * The set of records an AI summary may reason about.
 *
 * Every subject resolves to the same shape — which leads, which sales leads and
 * orders, and which of those form the "current" thread versus commercial history.
 * All shared gathering in AiContextBuilder works off this, so a new subject only
 * has to answer "what is relevant here?".
 *
 * The derived collections are memoised; a single build asks for them repeatedly.
 */
class AiContextScope
{
    /** @var Collection<int, Order>|null */
    private ?Collection $orders = null;

    /** @var Collection<int, int>|null */
    private ?Collection $orderIds = null;

    /** @var Collection<int, int>|null */
    private ?Collection $salesLeadIds = null;

    /**
     * @param  Collection<int, int>  $leadIds  Every lead that may contribute context.
     * @param  Collection<int, int>  $currentLeadIds  Leads considered the running thread.
     * @param  Collection<int, Lead>  $leads
     * @param  Collection<int, Lead>  $historicalLeads
     * @param  Collection<int, SalesLead>  $salesLeads
     * @param  Collection<int, SalesLead>  $currentSalesLeads
     * @param  Collection<int, int>  $currentOrderIds
     */
    public function __construct(
        public readonly Collection $leadIds,
        public readonly Collection $currentLeadIds,
        public readonly Collection $leads,
        public readonly Collection $historicalLeads,
        public readonly Collection $salesLeads,
        public readonly Collection $currentSalesLeads,
        public readonly Collection $currentOrderIds,
    ) {}

    /**
     * @return Collection<int, int>
     */
    public function historicalLeadIds(): Collection
    {
        return $this->leadIds->reject(fn (int $id) => $this->currentLeadIds->contains($id))->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function salesLeadIds(): Collection
    {
        return $this->salesLeadIds ??= $this->salesLeads->pluck('id')->values();
    }

    /**
     * @return Collection<int, Order>
     */
    public function orders(): Collection
    {
        return $this->orders ??= $this->salesLeads->flatMap->orders->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function orderIds(): Collection
    {
        return $this->orderIds ??= $this->orders()->pluck('id')->values();
    }

    /**
     * @return Collection<int, Order>
     */
    public function currentOrders(): Collection
    {
        return $this->orders()
            ->filter(fn (Order $order) => $this->currentOrderIds->contains($order->id))
            ->values();
    }

    /**
     * Orders outside the current thread that were actually executed; their notes are
     * already represented by the history entries and should not repeat in the timeline.
     *
     * @return Collection<int, int>
     */
    public function examinedHistoricalOrderIds(): Collection
    {
        return $this->orders()
            ->reject(fn (Order $order) => $this->currentOrderIds->contains($order->id))
            ->filter(fn (Order $order) => $order->first_examination_at !== null)
            ->pluck('id')
            ->values();
    }
}
