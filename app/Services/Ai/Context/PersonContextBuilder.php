<?php

namespace App\Services\Ai\Context;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * Context for one person: their whole relationship with us rather than a single
 * deal. No lead is "the" thread here, so every lead of this patient counts as
 * current and the timeline spans all of them.
 */
class PersonContextBuilder extends AiContextBuilder
{
    protected function resolveScope(Model $subject): AiContextScope
    {
        /** @var Person $subject */
        return $this->scopeForPersons(
            personIds: collect([$subject->id]),
            patientWide: true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function subjectEntry(Model $subject, AiContextScope $scope): array
    {
        /** @var Person $subject */
        $subject->loadMissing(['address', 'tags']);

        $source = $this->source(
            'person',
            $subject->id,
            'Contactpersoon: '.$subject->name,
            $subject->updated_at ?? $subject->created_at,
            'Laatst gewijzigd',
            null,
            [
                'updated_at' => $this->date($subject->updated_at),
                'name'       => $subject->name,
                'is_active'  => $subject->is_active,
            ],
        );

        return [
            'id'                 => $subject->id,
            'updated_at'         => $this->date($subject->updated_at),
            'name'               => $subject->name,
            'age'                => $subject->age,
            'gender'             => $this->enumValue($subject->gender),
            'preferred_language' => $this->enumValue($subject->preferred_language),
            'city'               => $subject->address?->city,
            'job_title'          => $subject->job_title,
            'inactive'           => $subject->is_active ? null : true,
            'tags'               => $subject->tags->pluck('name')->filter()->values()->all(),
            'ref'                => $source['ref'] ?? null,
            '_source'            => $source,
        ];
    }

    /**
     * Relationship totals the model cannot infer from the timeline alone: is this a
     * returning patient, what has been spent, and is anything still scheduled?
     *
     * @return array<string, mixed>
     */
    protected function extraBlocks(Model $subject, AiContextScope $scope): array
    {
        $orders = $scope->orders();
        $examined = $orders->filter(fn (Order $order) => $order->first_examination_at?->isPast() === true);

        $lastExamination = $examined
            ->map(fn (Order $order) => $order->first_examination_at)
            ->filter()
            ->max();

        $openLeads = $scope->leads->filter(
            fn (Lead $lead) => ! ($lead->stage?->is_won || $lead->stage?->is_lost)
        );

        return [
            'relationship' => array_filter([
                'lead_count'          => $scope->leads->count(),
                'open_lead_count'     => $openLeads->count(),
                'order_count'         => $orders->count(),
                'examined_count'      => $examined->count(),
                'lifetime_value'      => round((float) $examined->sum(fn (Order $order) => (float) $order->total_price), 2),
                'last_examination_at' => $this->dateOnly($lastExamination),
            ], fn ($value) => ! empty($value)),

            // Patient-wide scopes treat exactly the still-upcoming orders as current.
            'upcoming_orders' => $scope->currentOrders()
                ->sortBy(fn (Order $order) => $order->first_examination_at?->getTimestamp() ?? 0)
                ->map(fn (Order $order) => $this->orderEntry($order))
                ->values()
                ->all(),
        ];
    }
}
