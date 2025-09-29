<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ResourceDataGrid;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTypeRepository;
use App\Repositories\ShiftRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResourceController extends SimpleEntityController
{
    public function __construct(
        protected ResourceRepository $resourceRepository,
        protected ResourceTypeRepository $resourceTypeRepository,
        protected ShiftRepository $shiftRepository
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
		$upcomingShifts = $this->shiftRepository->upcomingForResource($resource->id, 50);

		$scheduleSummary = $this->buildMergedWeeklySummary($upcomingShifts->all());

		return view('admin::settings.resources.show', [
            'resource'       => $resource,
            'upcomingShifts' => $upcomingShifts,
			'scheduleSummary'=> $scheduleSummary,
        ]);
    }

	/**
	 * Build a merged weekly summary of availability/unavailability.
	 *
	 * @param array<int, \App\Models\Shift> $shifts
	 * @return array<int, array{available: array<int, array{from: string, to: string}>, unavailable: array<int, array{from: string, to: string}>}>
	 */
	protected function buildMergedWeeklySummary(array $shifts): array
	{
		// Initialize summary for days 1..7 (Mon..Sun)
		$summary = [];
		for ($day = 1; $day <= 7; $day++) {
			$summary[$day] = [
				'available' => [],
				'unavailable' => [],
			];
		}

		foreach ($shifts as $shift) {
			$blocksByDay = (array) ($shift->weekday_time_blocks ?? []);
			$available = (bool) ($shift->available ?? true);

			foreach ($blocksByDay as $day => $blocks) {
				if (!is_array($blocks)) {
					continue;
				}

				foreach ($blocks as $block) {
					$from = isset($block['from']) ? (string) $block['from'] : null;
					$to = isset($block['to']) ? (string) $block['to'] : null;
					if (!$from || !$to) {
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

		// Merge overlaps inside each day's available/unavailable lists
		for ($day = 1; $day <= 7; $day++) {
			$summary[$day]['available'] = $this->mergeOverlappingTimeRanges($summary[$day]['available']);
			$summary[$day]['unavailable'] = $this->mergeOverlappingTimeRanges($summary[$day]['unavailable']);
		}

		return $summary;
	}

	/**
	 * Merge overlapping time ranges within a single day.
	 *
	 * Input/Output format: array of ['from' => 'HH:MM', 'to' => 'HH:MM']
	 *
	 * @param array<int, array{from: string, to: string}> $ranges
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

    protected function getCreateViewData(Request $request): array
    {
        return [
            'resourceTypes' => $this->resourceTypeRepository->all(),
        ];
    }

    protected function getEditViewData(Request $request, Model $entity): array
    {
        return [
            'resource'      => $entity,
            'resourceTypes' => $this->resourceTypeRepository->all(),
        ];
    }

    protected function validateStore(Request $request): void
    {
        $request->validate([
            'name'              => 'required|unique:resources,name|max:100',
            'resource_type_id'  => 'required|exists:resource_types,id',
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate([
            'name'              => 'required|max:100|unique:resources,name,'.$id,
            'resource_type_id'  => 'required|exists:resource_types,id',
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
