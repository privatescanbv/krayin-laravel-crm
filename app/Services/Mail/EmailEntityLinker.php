<?php

namespace App\Services\Mail;

use App\Models\Clinic;
use App\Models\Order;
use App\Models\SalesLead;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

class EmailEntityLinker
{
    /**
     * Link email data to existing entities based on sender email address.
     *
     * Priority when a matching Person is found:
     *   1. Active Order  → sets order_id only (person accessible via order → salesLead → persons)
     *   2. Active SalesLead → sets sales_lead_id only
     *   3. Active Lead   → sets lead_id + person_id
     *   4. Fallback      → sets person_id only
     *
     * Clinic matching is independent and may coexist with any other link.
     */
    public function link(array $emailData, string $emailAddress): array
    {
        if (empty($emailAddress)) {
            return $emailData;
        }

        $person = Person::where('emails', 'like', '%'.$emailAddress.'%')->first();

        if ($person) {
            $order = Order::query()
                ->forPerson($person)
                ->inOpenStage()
                ->latest()
                ->first();

            if ($order) {
                $emailData['order_id'] = $order->id;
            } elseif ($salesLead = SalesLead::whereHas('persons', fn ($q) => $q->where('persons.id', $person->id))
                ->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))
                ->latest()
                ->first()) {
                $emailData['sales_lead_id'] = $salesLead->id;
            } else {
                $lead = Lead::whereHas('persons', fn ($q) => $q->where('persons.id', $person->id))
                    ->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))
                    ->latest()
                    ->first();

                if ($lead) {
                    $emailData['lead_id'] = $lead->id;
                } else {
                    $emailData['person_id'] = $person->id;
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
