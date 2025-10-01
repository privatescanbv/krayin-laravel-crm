<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ResourceDataGrid;
use App\Repositories\ClinicRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTypeRepository;
use App\Repositories\ShiftRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResourceController extends SimpleEntityController
{
    public function __construct(
        protected ResourceRepository $resourceRepository,
        protected ResourceTypeRepository $resourceTypeRepository,
        protected ShiftRepository $shiftRepository,
        protected ClinicRepository $clinicRepository
    ) {
        parent::__construct($resourceRepository);

        $this->entityName = 'resource';
        $this->datagridClass = ResourceDataGrid::class;
        $this->indexView = 'admin::settings.resources.index';
        $this->createView = 'admin::settings.resources.create';
        $this->editView = 'admin::settings.resources.edit';
        $this->indexRoute = 'admin.settings.resources.index';
        $this->permissionPrefix = 'settings.resources';
    }

    public function show(int $id): View
    {
        $resource = $this->resourceRepository->findOrFail($id);
        // Load all shifts for accurate period summaries (not only upcoming)
        $allShifts = $this->shiftRepository->forResource($resource->id)
            ->orderBy('period_start')
            ->get();

        // Build period-aware weekly summaries expected by the Blade view
        $periodSummaries = $this->buildPeriodAwareWeeklySummaries($allShifts->all());

        return view('admin::settings.resources.show', [
            'resource'         => $resource,
            'upcomingShifts'   => collect(),
            'scheduleSummary'  => $this->buildMergedWeeklySummary($allShifts->all()), // keep for potential partials
            'periodSummaries'  => $periodSummaries,
        ]);
    }

    /**
     * Filter resources by clinic IDs.
     */
    public function filterByClinics(Request $request): JsonResponse
    {
        $clinicIds = $request->input('clinic_ids', []);

        if (empty($clinicIds)) {
            return response()->json(['data' => []]);
        }

        $resources = $this->resourceRepository
            ->scopeQuery(function ($query) use ($clinicIds) {
                return $query->whereIn('clinic_id', $clinicIds)
                    ->orderBy('name')
                    ->select('id', 'name', 'clinic_id');
            })
            ->all();

        return response()->json(['data' => $resources]);
    }

    /**
     * Build a merged weekly summary of availability/unavailability.
     *
     * @param  array<int, \App\Models\Shift>  $shifts
     * @return array<int, array{available: array<int, array{from: string, to: string}>, unavailable: array<int, array{from: string, to: string}>}>
     */
    protected function buildMergedWeeklySummary(array $shifts): array
    {
        // Initialize summary for days 1..7 (Mon..Sun)
        $summary = [];
        for ($day = 1; $day <= 7; $day++) {
            $summary[$day] = [
                'available'   => [],
                'unavailable' => [],
            ];
        }

        foreach ($shifts as $shift) {
            $blocksByDay = (array) ($shift->weekday_time_blocks ?? []);
            $available = (bool) ($shift->available ?? true);

            foreach ($blocksByDay as $day => $blocks) {
                if (! is_array($blocks)) {
                    continue;
                }

                foreach ($blocks as $block) {
                    $from = isset($block['from']) ? (string) $block['from'] : null;
                    $to = isset($block['to']) ? (string) $block['to'] : null;
                    if (! $from || ! $to) {
                        continue;
                    }

                    $entry = ['from' => $from, 'to' => $to];
                    if ($available) {
                        $summary[$day]['available'][] = $entry;
                    } else {
                        $summary[$day]['unavailable'][] = $entry;
                    }
                }
            }
        }

        // Merge overlaps and compute net available by subtracting unavailable
        for ($day = 1; $day <= 7; $day++) {
            $mergedAvailable = $this->mergeOverlappingTimeRanges($summary[$day]['available']);
            $mergedUnavailable = $this->mergeOverlappingTimeRanges($summary[$day]['unavailable']);

            $summary[$day]['available'] = $this->subtractTimeRanges($mergedAvailable, $mergedUnavailable);
            $summary[$day]['unavailable'] = $mergedUnavailable;
        }

        return $summary;
    }

    /**
     * Build per-period weekly summaries by segmenting overlapping date periods
     * into non-overlapping ranges with a constant set of active shifts.
     *
     * @param  array<int, \App\Models\Shift>  $shifts
     * @return array<int, array{label: string, start: ?string, end: ?string, summary: array<int, array{available: array<int, array{from: string, to: string}>, unavailable: array<int, array{from: string, to: string}>}>}>
     */
    protected function buildPeriodAwareWeeklySummaries(array $shifts): array
    {
        if (empty($shifts)) {
            return [];
        }

        // Collect timeline boundary events: start at period_start, end at day after period_end
        $events = [];
        $today = now()->startOfDay();
        $maxLookahead = $today->copy()->addMonths(18); // cap open-ended at 18 months for display

        foreach ($shifts as $idx => $shift) {
            $start = optional($shift->period_start)->startOfDay() ?? $today;
            $end = $shift->period_end ? $shift->period_end->copy()->addDay()->startOfDay() : null; // end exclusive

            $events[] = ['date' => $start, 'type' => 'start', 'idx' => $idx];
            if ($end) {
                $events[] = ['date' => $end, 'type' => 'end', 'idx' => $idx];
            }
        }

        if (empty($events)) {
            return [];
        }

        usort($events, function ($a, $b) {
            if ($a['date']->eq($b['date'])) {
                return $a['type'] === 'end' ? -1 : 1; // end before start on same day
            }

            return $a['date'] <=> $b['date'];
        });

        $active = [];
        $segments = [];
        for ($i = 0; $i < count($events); $i++) {
            $event = $events[$i];
            $date = $event['date'];
            $type = $event['type'];
            $idx = $event['idx'];

            if ($type === 'end') {
                unset($active[$idx]);
            } else {
                $active[$idx] = true;
            }

            $nextDate = $i + 1 < count($events) ? $events[$i + 1]['date'] : $maxLookahead;
            if (! empty($active) && $date < $nextDate) {
                $segmentShiftIndexes = array_keys($active);
                $segments[] = [
                    'from'   => $date->copy(),
                    'to'     => $nextDate ? $nextDate->copy() : null,
                    'shifts' => $segmentShiftIndexes,
                ];
            }
        }

        // Merge adjacent segments with identical active shift sets
        $normalized = [];
        foreach ($segments as $seg) {
            $signature = implode(',', $seg['shifts']);
            $last = end($normalized);
            if ($last !== false && $last['signature'] === $signature && $last['to']->eq($seg['from'])) {
                $normalized[key($normalized)]['to'] = $seg['to'];
            } else {
                $normalized[] = [
                    'from'      => $seg['from'],
                    'to'        => $seg['to'],
                    'shifts'    => $seg['shifts'],
                    'signature' => $signature,
                ];
            }
        }

        $result = [];
        foreach ($normalized as $seg) {
            $segmentShifts = [];
            foreach ($seg['shifts'] as $sIdx) {
                $segmentShifts[] = $shifts[$sIdx];
            }

            $summary = $this->buildMergedWeeklySummary($segmentShifts);

            $fromStr = $seg['from'] ? $seg['from']->format('Y-m-d') : null;
            $toStr = $seg['to'] ? $seg['to']->copy()->subDay()->format('Y-m-d') : null; // convert exclusive end back to inclusive
            $label = $fromStr && $toStr ? ($fromStr.' — '.$toStr) : ($fromStr.' — ∞');

            $result[] = [
                'label'   => $label,
                'start'   => $fromStr,
                'end'     => $toStr,
                'summary' => $summary,
            ];
        }

        return $result;
    }

    /**
     * Merge overlapping time ranges within a single day.
     *
     * Input/Output format: array of ['from' => 'HH:MM', 'to' => 'HH:MM']
     *
     * @param  array<int, array{from: string, to: string}>  $ranges
     * @return array<int, array{from: string, to: string}>
     */
    protected function mergeOverlappingTimeRanges(array $ranges): array
    {
        if (empty($ranges)) {
            return [];
        }

        usort($ranges, function ($a, $b) {
            return strcmp($a['from'], $b['from']);
        });

        $merged = [];
        $current = $ranges[0];

        for ($i = 1; $i < count($ranges); $i++) {
            $next = $ranges[$i];
            if ($next['from'] <= $current['to']) {
                // Overlaps or touches: extend the current range
                if ($next['to'] > $current['to']) {
                    $current['to'] = $next['to'];
                }
            } else {
                $merged[] = $current;
                $current = $next;
            }
        }

        $merged[] = $current;

        return $merged;
    }

    /**
     * Subtract unavailable ranges from available ranges within a day,
     * returning the net available ranges.
     *
     * @param  array<int, array{from: string, to: string}>  $available
     * @param  array<int, array{from: string, to: string}>  $unavailable
     * @return array<int, array{from: string, to: string}>
     */
    protected function subtractTimeRanges(array $available, array $unavailable): array
    {
        if (empty($available)) {
            return [];
        }
        if (empty($unavailable)) {
            return $available;
        }

        // Inputs should be merged and sorted
        $available = $this->mergeOverlappingTimeRanges($available);
        $unavailable = $this->mergeOverlappingTimeRanges($unavailable);

        $result = [];
        foreach ($available as $a) {
            $segments = [[$a['from'], $a['to']]];
            foreach ($unavailable as $u) {
                $newSegments = [];
                foreach ($segments as [$sf, $st]) {
                    // no overlap
                    if ($u['to'] <= $sf || $u['from'] >= $st) {
                        $newSegments[] = [$sf, $st];

                        continue;
                    }
                    // left remainder
                    if ($u['from'] > $sf) {
                        $newSegments[] = [$sf, min($st, $u['from'])];
                    }
                    // right remainder
                    if ($u['to'] < $st) {
                        $newSegments[] = [max($sf, $u['to']), $st];
                    }
                }
                $segments = $newSegments;
                if (empty($segments)) {
                    break;
                }
            }
            foreach ($segments as [$sf, $st]) {
                if ($sf < $st) {
                    $result[] = ['from' => $sf, 'to' => $st];
                }
            }
        }

        return $this->mergeOverlappingTimeRanges($result);
    }

    protected function getCreateViewData(Request $request): array
    {
        return [
            'resourceTypes'       => $this->resourceTypeRepository->all(),
            'clinics'             => $this->clinicRepository->all(),
            'preSelectedClinicId' => $request->query('clinic_id'),
        ];
    }

    protected function getEditViewData(Request $request, Model $entity): array
    {
        return [
            'resource'      => $entity,
            'resourceTypes' => $this->resourceTypeRepository->all(),
            'clinics'       => $this->clinicRepository->all(),
        ];
    }

    protected function validateStore(Request $request): void
    {
        $request->validate([
            'name'              => 'required|unique:resources,name|max:100',
            'resource_type_id'  => 'required|exists:resource_types,id',
            'clinic_id'         => 'required|exists:clinics,id',
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate([
            'name'              => 'required|max:100|unique:resources,name,'.$id,
            'resource_type_id'  => 'required|exists:resource_types,id',
            'clinic_id'         => 'required|exists:clinics,id',
        ]);
    }

    protected function getCreateSuccessMessage(): string
    {
        return trans('admin::app.settings.resources.index.create-success');
    }

    protected function getUpdateSuccessMessage(): string
    {
        return trans('admin::app.settings.resources.index.update-success');
    }

    protected function getDestroySuccessMessage(): string
    {
        return trans('admin::app.settings.resources.index.destroy-success');
    }

    protected function getDeleteFailedMessage(): string
    {
        return trans('admin::app.settings.resources.index.delete-failed');
    }
}
