<?php

namespace Webkul\Admin\Helpers\Reporting;

use Illuminate\Support\Facades\DB;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\StageRepository;

class Lead extends AbstractReporting
{
    /**
     * The channel ids.
     */
    protected array $stageIds;

    /**
     * The all stage ids.
     */
    protected array $allStageIds;

    /**
     * The won stage ids.
     */
    protected array $wonStageIds;

    /**
     * The lost stage ids.
     */
    protected array $lostStageIds;

    /**
     * Create a helper instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected StageRepository $stageRepository
    ) {
        $this->allStageIds = $this->stageRepository->pluck('id')->toArray();

        $this->wonStageIds = $this->stageRepository->where('code', 'won')->pluck('id')->toArray();

        $this->lostStageIds = $this->stageRepository->where('code', 'lost')->pluck('id')->toArray();

        parent::__construct();
    }

    /**
     * Returns current customers over time
     *
     * @param  string  $period
     */
    public function getTotalLeadsOverTime($period = 'auto'): array
    {
        $this->stageIds = $this->allStageIds;

        return $this->getOverTimeStats($this->startDate, $this->endDate, 'leads.id', 'created_at', $period);
    }

    /**
     * Returns current customers over time
     *
     * @param  string  $period
     */
    public function getTotalWonLeadsOverTime($period = 'auto'): array
    {
        $this->stageIds = $this->wonStageIds;

        return $this->getOverTimeStats($this->startDate, $this->endDate, 'leads.id', 'closed_at', $period);
    }

    /**
     * Returns current customers over time
     *
     * @param  string  $period
     */
    public function getTotalLostLeadsOverTime($period = 'auto'): array
    {
        $this->stageIds = $this->lostStageIds;

        return $this->getOverTimeStats($this->startDate, $this->endDate, 'leads.id', 'closed_at', $period);
    }

    /**
     * Retrieves total leads and their progress.
     */
    public function getTotalLeadsProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalLeads($this->lastStartDate, $this->lastEndDate),
            'current'  => $current = $this->getTotalLeads($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves total leads by date
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     */
    public function getTotalLeads($startDate, $endDate): int
    {
        return $this->leadRepository
            ->resetModel()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Retrieves average leads per day and their progress.
     */
    public function getAverageLeadsPerDayProgress(): array
    {
        return [
            'previous' => $previous = $this->getAverageLeadsPerDay($this->lastStartDate, $this->lastEndDate),
            'current'  => $current = $this->getAverageLeadsPerDay($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves average leads per day
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     */
    public function getAverageLeadsPerDay($startDate, $endDate): float
    {
        $days = $startDate->diffInDays($endDate);

        if ($days == 0) {
            return 0;
        }

        return $this->getTotalLeads($startDate, $endDate) / $days;
    }

    // Lead value methods removed - field no longer exists











    /**
     * Retrieves open leads by states.
     */
    public function getOpenLeadsByStates()
    {
        return $this->leadRepository
            ->resetModel()
            ->select(
                'lead_pipeline_stages.name',
                DB::raw('COUNT(*) as total')
            )
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNotIn('lead_pipeline_stage_id', $this->wonStageIds)
            ->whereNotIn('lead_pipeline_stage_id', $this->lostStageIds)
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->groupBy('lead_pipeline_stage_id')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Returns over time stats.
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     * @param  string  $valueColumn
     * @param  string  $period
     */
    public function getOverTimeStats($startDate, $endDate, $valueColumn, $dateColumn = 'created_at', $period = 'auto'): array
    {
        $config = $this->getTimeInterval($startDate, $endDate, $dateColumn, $period);

        $groupColumn = $config['group_column'];

        $query = $this->leadRepository
            ->resetModel()
            ->select(
                DB::raw("$groupColumn AS date"),
                DB::raw(DB::getTablePrefix()."$valueColumn AS total"),
                DB::raw('COUNT(*) AS count')
            )
            ->whereIn('lead_pipeline_stage_id', $this->stageIds)
            ->whereBetween($dateColumn, [$startDate, $endDate])
            ->groupBy('date');

        if (! empty($stageIds)) {
            $query->whereIn('lead_pipeline_stage_id', $stageIds);
        }

        $results = $query->get();

        foreach ($config['intervals'] as $interval) {
            $total = $results->where('date', $interval['filter'])->first();

            $stats[] = [
                'label' => $interval['start'],
                'total' => $total?->total ?? 0,
                'count' => $total?->count ?? 0,
            ];
        }

        return $stats ?? [];
    }
}
