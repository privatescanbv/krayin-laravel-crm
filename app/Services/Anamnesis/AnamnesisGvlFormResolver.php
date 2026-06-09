<?php

namespace App\Services\Anamnesis;

use App\Enums\FormStatus;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Models\Order;
use Illuminate\Support\Collection;

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
            ->with('gvlForms')
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
            ->filter(fn (AnamnesisGvlForm $f) => $f->gvl_form_status === FormStatus::Completed)
            ->sortByDesc('id')
            ->values();
    }
}
