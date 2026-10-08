<?php

namespace App\Actions\Leads;

use App\Services\CustomerHistory\CustomerHistoryService;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Lead;

/**
 * Stores the customer history as it stood when the lead came in. Always measured at
 * leads.created_at, so re-running it (person linked later, backfill) gives the same
 * answer and a later purchase never rewrites an older lead.
 */
class SnapshotLeadCustomerTypeAction
{
    public function __construct(
        private readonly CustomerHistoryService $customerHistory,
    ) {}

    public function execute(Lead $lead): void
    {
        $history = $this->customerHistory->forLead($lead);

        // Query builder on purpose: no observers, no AI refresh, no updated_at bump.
        DB::table('leads')->where('id', $lead->id)->update([
            'customer_type'               => $history?->customerType->value,
            'prior_lead_count'            => $history?->priorLeadCount,
            'prior_purchase_count'        => $history?->purchaseCount,
            'last_purchase_at'            => $history?->lastPurchaseAt?->toDateString(),
            'customer_type_determined_at' => $history ? now() : null,
        ]);
    }

    /**
     * Re-derive every lead of these persons. For merges, which rewrite lead_persons and
     * order_items directly and so change the history of leads the pivot events never see.
     *
     * @param  iterable<int>  $personIds
     */
    public function executeForPersons(iterable $personIds): void
    {
        Lead::query()
            ->whereIn('id', DB::table('lead_persons')->select('lead_id')->whereIn('person_id', collect($personIds)))
            ->select(['id', 'created_at'])
            ->each(fn (Lead $lead) => $this->execute($lead));
    }
}
