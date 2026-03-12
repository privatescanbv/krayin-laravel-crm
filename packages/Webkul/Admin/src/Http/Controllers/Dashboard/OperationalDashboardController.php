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
        $department = request()->query('department');
        if ($department && ! in_array($department, Departments::allValues(), true)) {
            $department = null;
        }

        $queues = [];

        foreach ($this->queueRegistry->all() as $definition) {
            $key = $definition['key'];

            $counts = $this->queueRepository->counts($key, $department);

            $queues[] = [
                'key'     => $key,
                'label'   => $definition['label'],
                'open'    => $counts['open'],
                'overdue' => $counts['overdue'],
            ];
        }

        $defaultQueueKey = $queues[0]['key'] ?? 'frontoffice';


        $defaultDepartment = $this->getInitialDepartmentForCurrentUser();

        return view('admin::dashboard.operational.index', [
            'queues'            => $queues,
            'defaultQueueKey'   => $defaultQueueKey,
            'defaultDepartment' => $defaultDepartment,
        ]);
    }

    /**
     * JSON endpoint returning open/overdue counts per queue, optionally filtered by department.
     */
    public function getCounts(Request $request): JsonResponse
    {
        $department = $request->query('department');
        if ($department && ! in_array($department, Departments::allValues(), true)) {
            $department = $this->getInitialDepartmentForCurrentUser();
        }

        $queues = [];
        foreach ($this->queueRegistry->all() as $definition) {
            $counts = $this->queueRepository->counts($definition['key'], $department);
            $queues[] = [
                'key'     => $definition['key'],
                'open'    => $counts['open'],
                'overdue' => $counts['overdue'],
            ];
        }

        return response()->json($queues);
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

    private function getInitialDepartmentForCurrentUser(): string
    {
        $user = auth()->guard('user')->user();
        $departmentNames = $user
            ? $user->groups()->with('department')->get()
                ->map(fn ($g) => $g->department?->name)
                ->filter()->unique()->values()
            : collect();

        $deptCase = $departmentNames->count() === 1
            ? collect(Departments::cases())->first(fn ($d) => $d->value === $departmentNames->first())
            : null;

        return $deptCase ? $deptCase->key() : Departments::PRIVATESCAN->key();
    }
}

