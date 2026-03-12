<?php

namespace Webkul\Admin\Http\Controllers\Dashboard;

use App\Enums\Departments;
use App\Services\ActivityQueueRegistry;
use App\Services\ActivityQueueRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Activity\ActivityDataGrid;
use Webkul\Admin\Http\Controllers\Controller;

class OperationalDashboardController extends Controller
{
    public function __construct(
        protected ActivityQueueRegistry $queueRegistry,
        protected ActivityQueueRepository $queueRepository,
    ) {
    }

    /**
     * Show the operational dashboard with all queues.
     */
    public function index(): View
    {
        $queues = [];

        foreach ($this->queueRegistry->all() as $definition) {
            $key = $definition['key'];

            $counts = $this->queueRepository->counts($key);

            $queues[] = [
                'key'     => $key,
                'label'   => $definition['label'],
                'open'    => $counts['open'],
                'overdue' => $counts['overdue'],
            ];
        }

        $defaultQueueKey = $queues[0]['key'] ?? 'frontoffice';

        $user = auth()->guard('user')->user();
        $departmentNames = $user
            ? $user->groups()->with('department')->get()
                ->map(fn ($g) => $g->department?->name)
                ->filter()->unique()->values()
            : collect();

        $deptCase = $departmentNames->count() === 1
            ? collect(Departments::cases())->first(fn ($d) => $d->value === $departmentNames->first())
            : null;

        $defaultDepartment = $deptCase ? $deptCase->key() : Departments::PRIVATESCAN->key();

        return view('admin::dashboard.operational.index', [
            'queues'            => $queues,
            'defaultQueueKey'   => $defaultQueueKey,
            'defaultDepartment' => $defaultDepartment,
        ]);
    }

    /**
     * JSON endpoint backing the ActivityDataGrid for a specific queue.
     */
    public function getQueue(Request $request): JsonResponse
    {
        $queueKey = $request->query('queue', $request->input('queue', 'frontoffice'));

        // Ensure queue exists (will throw if unknown).
        $definition = $this->queueRegistry->get($queueKey);

        $request->merge([
            'queue' => $definition['key'],
        ]);

        // Only apply queue-specific default sorting when the caller did not request a sort.
        if (! $request->has('sort')) {
            $sort = $this->queueRegistry->getSort($queueKey);

            if ($sort) {
                $request->merge([
                    'sort' => $sort,
                ]);
            }
        }

        return datagrid(ActivityDataGrid::class)->process();
    }
}

