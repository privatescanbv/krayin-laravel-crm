<?php

namespace Webkul\Admin\Helpers\Reporting;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Repositories\PersonRepository;

class Person extends AbstractReporting
{
    /**
     * Create a helper instance.
     *
     * @return void
     */
    public function __construct(protected PersonRepository $personRepository)
    {
        parent::__construct();
    }

    /**
     * Retrieves total persons and their progress.
     */
    public function getTotalPersonsProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalPersons($this->lastStartDate, $this->lastEndDate),
            'current'  => $current = $this->getTotalPersons($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves total persons by date
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     */
    public function getTotalPersons($startDate, $endDate): int
    {
        return $this->personRepository
            ->resetModel()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Returns top customers by revenue.
     *
     * @param  int  $limit
     * @return \Illuminate\Support\Collection
     */
    public function getTopCustomersByRevenue($limit = null): Collection
    {
        // Get all won stage IDs (including won-hernia, etc.)
        $wonStageIds = DB::table('lead_pipeline_stages')->where('code', 'like', '%won%')->pluck('id')->toArray();

        $items = $this->personRepository
            ->resetModel()
            ->leftJoin('lead_persons', 'persons.id', '=', 'lead_persons.person_id')
            ->leftJoin('leads', 'lead_persons.lead_id', '=', 'leads.id')
            ->select('persons.*', 'persons.id as id')
            ->addSelect(DB::raw('COUNT(leads.id) as won_leads_count'))
            ->whereIn('leads.lead_pipeline_stage_id', $wonStageIds)
            ->whereBetween('leads.closed_at', [$this->startDate, $this->endDate])
            ->having('won_leads_count', '>', 0)
            ->groupBy('persons.id')
            ->orderBy('won_leads_count', 'DESC')
            ->limit($limit)
            ->get();

        $items = $items->map(function ($item) {
            return [
                'id'                => $item->id,
                'name'              => $item->name,
                'emails'            => $item->emails,
                'phones'            => $item->phones,
                'won_leads_count'   => $item->won_leads_count,
                'formatted_count'   => $item->won_leads_count . ' gewonnen leads',
            ];
        });

        return $items;
    }
}
