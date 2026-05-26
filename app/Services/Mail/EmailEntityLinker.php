<?php

namespace App\Services\Mail;

use App\Models\Clinic;
use App\Models\SalesLead;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

class EmailEntityLinker
{
    /**
     * Link email data to existing entities based on sender email address.
     *
     * When an active sales lead is found for the sender, only {@code sales_lead_id} is set.
     * Person/lead links are omitted because the UI resolves those upward from sales.
     * Orders are never auto-linked here — an active sales may have multiple orders and only
     * the employee can pick the right one ({@see EmailLlmLinkingService::activeOrderSuggestionsForSalesLead}).
     * Clinic matching is independent and may coexist with any other link.
     */
    public function link(array $emailData, string $emailAddress): array
    {
        if (empty($emailAddress)) {
            return $emailData;
        }

        $person = Person::where('emails', 'like', '%'.$emailAddress.'%')->first();

        if ($person) {
            $salesLead = SalesLead::whereHas('persons', fn ($q) => $q->where('persons.id', $person->id))
                ->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))
                ->latest()
                ->first();

            if ($salesLead) {
                $emailData['sales_lead_id'] = $salesLead->id;
            } else {
                $emailData['person_id'] = $person->id;

                $lead = Lead::whereHas('persons', fn ($q) => $q->where('persons.id', $person->id))
                    ->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))
                    ->latest()
                    ->first();

                if ($lead) {
                    $emailData['lead_id'] = $lead->id;
                }
            }
        } else {
            $lead = Lead::where('emails', 'like', '%'.$emailAddress.'%')
                ->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))
                ->latest()
                ->first();

            if ($lead) {
                $emailData['lead_id'] = $lead->id;
            }
        }

        $clinic = Clinic::where('emails', 'like', '%'.$emailAddress.'%')->first();

        if ($clinic) {
            $emailData['clinic_id'] = $clinic->id;
        }

        return $emailData;
    }
}
