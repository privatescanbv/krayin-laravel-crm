<?php

namespace App\Services\CustomerHistory;

use App\Enums\ActivityType;
use App\Enums\CallStatus;
use App\Enums\CustomerType;
use App\Enums\OrderItemStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * Single source of truth for "who is this person to us": new, returning buyer or
 * returning non-buyer, plus the counts behind it. Lead snapshots, the AI context and
 * the analytics sync all read from here so their numbers never drift apart.
 *
 * Definitions:
 *  - purchase:        a non-lost order item for this person on an order in a won stage,
 *                     dated by the order's closed_at (created_at when not closed).
 *  - prior lead:      another lead of this person (contact person or lead_persons),
 *                     created before the reference moment.
 *  - sales activity:  call/task activity or e-mail sent by us on a prior lead or one of
 *                     its sales leads.
 *  - contact:         a call marked "spoken", or an e-mail received from the person.
 */
class CustomerHistoryService
{
    private const SALES_ACTIVITY_TYPES = [ActivityType::CALL, ActivityType::TASK];

    /**
     * @param  CarbonInterface|null  $asOf  only count what happened before this moment (null = now)
     * @param  int|null  $excludeLeadId  the lead being classified, which is not its own prior lead
     */
    public function forPerson(Person|int $person, ?CarbonInterface $asOf = null, ?int $excludeLeadId = null): CustomerHistory
    {
        $personId = $person instanceof Person ? $person->id : $person;
        $priorLeadIds = $this->priorLeadsQuery($personId, $asOf, $excludeLeadId)->pluck('id');

        $priorLostLeadCount = $priorLeadIds->isEmpty()
            ? 0
            : Lead::query()
                ->whereIn('id', $priorLeadIds)
                ->whereHas('stage', fn (Builder $stage) => $stage->where('is_lost', true))
                ->count();

        $purchases = $this->purchasesQuery($personId, $asOf)
            ->selectRaw('COUNT(DISTINCT o.id) as purchase_count')
            ->selectRaw('MIN(COALESCE(o.closed_at, o.created_at)) as first_purchase_at')
            ->selectRaw('MAX(COALESCE(o.closed_at, o.created_at)) as last_purchase_at')
            ->selectRaw('COALESCE(SUM(oi.total_price), 0) as revenue_total')
            ->first();

        $purchaseCount = (int) $purchases->purchase_count;

        return new CustomerHistory(
            customerType: match (true) {
                $purchaseCount > 0          => CustomerType::ExistingBuyer,
                $priorLeadIds->isNotEmpty() => CustomerType::ExistingNonBuyer,
                default                     => CustomerType::New,
            },
            priorLeadCount: $priorLeadIds->count(),
            priorLostLeadCount: $priorLostLeadCount,
            purchaseCount: $purchaseCount,
            firstPurchaseAt: $purchases->first_purchase_at ? CarbonImmutable::parse($purchases->first_purchase_at) : null,
            lastPurchaseAt: $purchases->last_purchase_at ? CarbonImmutable::parse($purchases->last_purchase_at) : null,
            revenueTotal: round((float) $purchases->revenue_total, 2),
            salesActivityCount: $this->salesActivityCount($priorLeadIds, $asOf),
        );
    }

    /**
     * The history as it stood when this lead came in. A lead can belong to several
     * persons (partners) without one being the main one, so the strongest history
     * wins: an earlier buyer outranks a returning non-buyer, which outranks new.
     * Null when no person is linked yet.
     */
    public function forLead(Lead $lead): ?CustomerHistory
    {
        return DB::table('lead_persons')
            ->where('lead_id', $lead->id)
            ->pluck('person_id')
            ->map(fn ($personId) => $this->forPerson((int) $personId, $lead->created_at, $lead->id))
            ->sortByDesc(fn (CustomerHistory $history) => [$history->customerType->rank(), $history->priorLeadCount])
            ->first();
    }

    /**
     * Whether anyone actually reached the person on this lead or its sales leads.
     */
    public function hadContact(Lead $lead): bool
    {
        $leadIds = collect([$lead->id]);
        $salesLeadIds = $this->salesLeadIds($leadIds);

        $spokenCall = DB::table('activities')
            ->where('type', ActivityType::CALL->value)
            ->where(fn (QueryBuilder $q) => $this->onLeadsOrSalesLeads($q, $leadIds, $salesLeadIds))
            ->whereExists(fn (QueryBuilder $q) => $q->from('call_statuses')
                ->whereColumn('call_statuses.activity_id', 'activities.id')
                ->where('call_statuses.status', CallStatus::SPOKEN->value))
            ->exists();

        return $spokenCall || DB::table('emails')
            ->where('user_type', 'person')
            ->where(fn (QueryBuilder $q) => $this->onLeadsOrSalesLeads($q, $leadIds, $salesLeadIds))
            ->exists();
    }

    private function priorLeadsQuery(int $personId, ?CarbonInterface $asOf, ?int $excludeLeadId): Builder
    {
        // Resolved up front: an OR against a subquery makes MySQL scan all of leads.
        $linkedLeadIds = DB::table('lead_persons')->where('person_id', $personId)->pluck('lead_id');

        return Lead::query()
            ->where(fn (Builder $q) => $q
                ->where('contact_person_id', $personId)
                ->orWhereIn('id', $linkedLeadIds))
            ->when($excludeLeadId, fn (Builder $q) => $q->whereKeyNot($excludeLeadId))
            ->when($asOf, fn (Builder $q) => $q->where('created_at', '<', $asOf));
    }

    private function purchasesQuery(int $personId, ?CarbonInterface $asOf): QueryBuilder
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('lead_pipeline_stages as s', 's.id', '=', 'o.pipeline_stage_id')
            ->where('oi.person_id', $personId)
            ->where('s.is_won', true)
            ->where(fn (QueryBuilder $q) => $q->whereNull('oi.status')->orWhere('oi.status', '!=', OrderItemStatus::LOST->value))
            ->when($asOf, fn (QueryBuilder $q) => $q->whereRaw('COALESCE(o.closed_at, o.created_at) < ?', [$asOf]));
    }

    /**
     * @param  Collection<int, int>  $leadIds
     */
    private function salesActivityCount(Collection $leadIds, ?CarbonInterface $asOf): int
    {
        if ($leadIds->isEmpty()) {
            return 0;
        }

        $salesLeadIds = $this->salesLeadIds($leadIds);

        $activities = DB::table('activities')
            ->whereIn('type', array_map(fn (ActivityType $type) => $type->value, self::SALES_ACTIVITY_TYPES))
            ->where(fn (QueryBuilder $q) => $this->onLeadsOrSalesLeads($q, $leadIds, $salesLeadIds))
            ->when($asOf, fn (QueryBuilder $q) => $q->where('created_at', '<', $asOf))
            ->count();

        $sentEmails = DB::table('emails')
            ->where('user_type', 'user')
            ->where(fn (QueryBuilder $q) => $this->onLeadsOrSalesLeads($q, $leadIds, $salesLeadIds))
            ->when($asOf, fn (QueryBuilder $q) => $q->where('created_at', '<', $asOf))
            ->count();

        return $activities + $sentEmails;
    }

    /**
     * @param  Collection<int, int>  $leadIds
     * @return Collection<int, int>
     */
    private function salesLeadIds(Collection $leadIds): Collection
    {
        return DB::table('salesleads')->whereIn('lead_id', $leadIds)->pluck('id');
    }

    /**
     * @param  Collection<int, int>  $leadIds
     * @param  Collection<int, int>  $salesLeadIds
     */
    private function onLeadsOrSalesLeads(QueryBuilder $query, Collection $leadIds, Collection $salesLeadIds): void
    {
        $query->whereIn('lead_id', $leadIds)
            ->when($salesLeadIds->isNotEmpty(), fn (QueryBuilder $q) => $q->orWhereIn('sales_lead_id', $salesLeadIds));
    }
}
