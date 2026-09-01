<?php

namespace App\Services\Anamnesis;

use App\Enums\FormStatus;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Support\Collection;
use Webkul\Lead\Models\Lead;

class AnamnesisGvlFormResolver
{
    /**
     * Load all anamnesis records for an order following the inheritance chain
     * (order-level, sales-level, lead-level) in one query, with gvlForms eager-loaded.
     */
    public function loadForOrder(Order $order): Collection
    {
        $salesLeadId = $order->sales_lead_id;
        $leadId = $order->salesLead?->lead_id;

        return Anamnesis::query()
            ->where('order_id', $order->id)
            ->when($salesLeadId, fn ($q) => $q->orWhere('sales_id', $salesLeadId))
            ->when($leadId, fn ($q) => $q->orWhere('lead_id', $leadId))
            ->with($this->anamnesisRelations())
            ->get();
    }

    /**
     * Load all anamnesis records for a sales lead (sales + parent lead + child orders).
     */
    public function loadForSales(SalesLead $salesLead): Collection
    {
        $leadId = $salesLead->lead_id;
        $orderIds = $salesLead->orders()->pluck('id');

        return Anamnesis::query()
            ->where('sales_id', $salesLead->id)
            ->when($leadId, fn ($q) => $q->orWhere('lead_id', $leadId))
            ->when($orderIds->isNotEmpty(), fn ($q) => $q->orWhereIn('order_id', $orderIds))
            ->with($this->anamnesisRelations())
            ->get();
    }

    /**
     * Load all anamnesis records for a lead (lead + downstream sales + their orders).
     */
    public function loadForLead(Lead $lead): Collection
    {
        $salesIds = SalesLead::query()->where('lead_id', $lead->id)->pluck('id');
        $orderIds = Order::query()->whereIn('sales_lead_id', $salesIds)->pluck('id');

        return Anamnesis::query()
            ->where('lead_id', $lead->id)
            ->when($salesIds->isNotEmpty(), fn ($q) => $q->orWhereIn('sales_id', $salesIds))
            ->when($orderIds->isNotEmpty(), fn ($q) => $q->orWhereIn('order_id', $orderIds))
            ->with($this->anamnesisRelations())
            ->get();
    }

    /**
     * Pick the most specific anamnesis for a given person on this order.
     * Priority: order-level → sales-level → lead-level.
     */
    public function resolveForPerson(Collection $allAnamneses, int $orderId, int $personId): ?Anamnesis
    {
        $personAnamneses = $allAnamneses->where('person_id', $personId);

        return $personAnamneses->firstWhere('order_id', $orderId)
            ?? $personAnamneses->first(fn ($a) => $a->sales_id && ! $a->order_id)
            ?? $personAnamneses->first(fn ($a) => ! $a->order_id && ! $a->sales_id);
    }

    /**
     * Return all completed GVL forms from an already-loaded anamnesis, newest first.
     */
    public function completedFormsForAnamnesis(?Anamnesis $anamnesis): Collection
    {
        if ($anamnesis === null) {
            return collect();
        }

        return ($anamnesis->relationLoaded('gvlForms') ? $anamnesis->gvlForms : $anamnesis->gvlForms()->get())
            ->filter(fn (AnamnesisGvlForm $f) => $f->gvl_form_status === FormStatus::Completed
                && ($f->gvl_form_type === null || $f->gvl_form_type->isGvlForm()))
            ->sortByDesc('id')
            ->values();
    }

    /**
     * @return list<string>
     */
    private function anamnesisRelations(): array
    {
        return ['gvlForms', 'lead', 'sales', 'order'];
    }
}
