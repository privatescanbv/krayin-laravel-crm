<?php

namespace Webkul\Admin\Http\Controllers\Reports;

use App\Enums\Departments;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\User\Models\User;

class RevenueByEmployeeController extends Controller
{
    /**
     * Afdelingen in weergavevolgorde. De filterwaarde op de wire is `Departments::key()`.
     *
     * @var list<Departments>
     */
    private const DEPARTMENTS = [
        Departments::PRIVATESCAN,
        Departments::HERNIA,
    ];

    private const PALETTE = [
        '#8979FF',
        '#FF928A',
        '#3CC3DF',
        '#F7C59F',
        '#6BCB77',
        '#FFD166',
        '#EF476F',
        '#118AB2',
        '#06D6A0',
        '#FF9F1C',
        '#A8DADC',
        '#E9C46A',
    ];

    public function index(Request $request): View
    {
        $period = $request->input('period', 'week') === 'month' ? 'month' : 'week';

        return view('admin::reports.revenue-by-employee.index', [
            'initialPeriod' => $period,
            'initialWeek'   => $request->integer('week', now()->isoWeek()),
            'initialYear'   => $request->integer('year', now()->year),
            'initialMonth'  => $request->input('month', now()->format('Y-m')),
        ]);
    }

    public function filterOptions(): JsonResponse
    {
        $stages = collect(PipelineStage::cases())
            ->filter(fn (PipelineStage $stage) => $stage->isOrder())
            ->map(fn (PipelineStage $stage) => [
                'id'         => $stage->id(),
                'label'      => $stage->label(),
                'department' => $this->departmentForPipeline($stage->pipeline())?->key(),
                'is_lost'    => $stage->isLost(),
            ])
            ->filter(fn (array $stage) => $stage['department'] !== null)
            ->values();

        $departments = collect(self::DEPARTMENTS)
            ->map(fn (Departments $department) => [
                'id'    => $department->key(),
                'label' => $department->value,
            ])
            ->values();

        return response()->json([
            'stages'      => $stages,
            'departments' => $departments,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $period = $request->query('period', 'week') === 'month' ? 'month' : 'week';

        [$periodStart, $periodEnd, $days, $periodMeta] = $this->resolvePeriod($request, $period);

        $departmentKeys = array_map(fn (Departments $department) => $department->key(), self::DEPARTMENTS);

        $selectedDepartments = $request->has('departments')
            ? array_values(array_intersect($this->arrayQuery($request, 'departments'), $departmentKeys))
            : $departmentKeys;

        $stageIds = collect(PipelineStage::cases())
            ->filter(fn (PipelineStage $stage) => $stage->isOrder())
            ->filter(fn (PipelineStage $stage) => in_array($this->departmentForPipeline($stage->pipeline())?->key(), $selectedDepartments, true))
            ->map(fn (PipelineStage $stage) => $stage->id())
            ->values()
            ->all();

        $requestedStageIds = array_values(array_filter(
            array_map('intval', $this->arrayQuery($request, 'stages')),
            fn (int $stageId) => $stageId > 0
        ));

        if ($request->has('stages')) {
            $stageIds = array_values(array_intersect($stageIds, $requestedStageIds));
        }

        $stageLabelMap = collect(PipelineStage::cases())
            ->filter(fn (PipelineStage $s) => $s->isOrder())
            ->mapWithKeys(fn (PipelineStage $s) => [$s->id() => $s->label()]);

        $wonStageIds = collect(PipelineStage::cases())
            ->filter(fn (PipelineStage $s) => $s->isOrder() && $s->statusCategory()?->value === 'won')
            ->map(fn (PipelineStage $s) => $s->id())
            ->all();

        $rows = empty($stageIds)
            ? collect()
            : Order::query()
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->whereIn('pipeline_stage_id', $stageIds)
                ->whereNotNull('user_id')
                ->select('user_id')
                ->selectRaw('DATE(created_at) as day')
                ->selectRaw('SUM(total_price) as total')
                ->groupBy('user_id', DB::raw('DATE(created_at)'))
                ->get();

        $ordersByUser = empty($stageIds)
            ? collect()
            : Order::query()
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->whereIn('pipeline_stage_id', $stageIds)
                ->whereNotNull('user_id')
                ->with([
                    'orderItems.purchasePrice',
                    'orderItems.product.partnerProducts.purchasePrice',
                ])
                ->orderBy('created_at')
                ->get(['id', 'order_number', 'title', 'total_price', 'created_at', 'pipeline_stage_id', 'user_id'])
                ->groupBy('user_id');

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id')->unique()->values())
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        $dayIndex = $days->pluck('date')->flip();
        $dayCount = $days->count();

        $groupedRows = $rows->groupBy('user_id');

        $datasets = [];
        $employees = [];

        foreach ($groupedRows as $userId => $userRows) {
            $user = $users->get((int) $userId);
            $color = self::PALETTE[count($datasets) % count(self::PALETTE)];
            $data = array_fill(0, $dayCount, 0.0);

            foreach ($userRows as $row) {
                $index = $dayIndex->get($row->day);

                if ($index !== null) {
                    $data[$index] = round((float) $row->total, 2);
                }
            }

            $name = $user?->name ?: 'Onbekende medewerker';
            $userOrders = $ordersByUser->get($userId) ?? collect();

            $datasets[] = [
                'label'           => $name,
                'data'            => $data,
                'backgroundColor' => $color,
                'borderRadius'    => 4,
                'user_id'         => (int) $userId,
            ];

            $employees[] = [
                'user_id' => (int) $userId,
                'name'    => $name,
                'color'   => $color,
                'bruto'   => round(array_sum($data), 2),
                'netto'   => round(
                    $userOrders
                        ->filter(fn ($o) => in_array($o->pipeline_stage_id, $wonStageIds, true))
                        ->sum(fn ($o) => (float) $o->total_price),
                    2
                ),
                'inkoop'  => round(
                    $userOrders->sum(fn ($o) => $o->totalPurchasePrice()),
                    2
                ),
                'orders'  => $userOrders
                    ->map(fn ($o) => [
                        'id'           => $o->id,
                        'label'        => $o->order_number ?: $o->title ?: "Order #{$o->id}",
                        'url'          => route('admin.orders.view', $o->id),
                        'created_at'   => $o->created_at->toDateString(),
                        'stage'        => $stageLabelMap->get($o->pipeline_stage_id, '—'),
                        'is_won'       => in_array($o->pipeline_stage_id, $wonStageIds, true),
                        'total_price'  => round((float) $o->total_price, 2),
                        'inkoop_price' => $o->totalPurchasePrice(),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        usort($employees, fn (array $a, array $b) => $b['bruto'] <=> $a['bruto']);

        return response()->json(array_merge([
            'period'       => $period,
            'period_label' => $periodMeta['period_label'],
            'days'         => $days,
            'datasets'     => $datasets,
            'employees'    => $employees,
        ], $periodMeta['response']));
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Collection<int, array{date: string, label: string, is_weekend: bool}>, 3: array{period_label: string, response: array<string, mixed>}}
     */
    private function resolvePeriod(Request $request, string $period): array
    {
        if ($period === 'month') {
            $month = (string) $request->query('month', now()->format('Y-m'));

            if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
                $month = now()->format('Y-m');
            }

            $periodStart = Carbon::parse("{$month}-01")->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            $days = collect();
            $cursor = $periodStart->copy();

            while ($cursor->lte($periodEnd)) {
                $date = $cursor->copy()->locale('nl');
                $days->push([
                    'date'       => $date->toDateString(),
                    'label'      => $date->isoFormat('D'),
                    'is_weekend' => $date->isWeekend(),
                ]);
                $cursor->addDay();
            }

            return [
                $periodStart,
                $periodEnd,
                $days->values(),
                [
                    'period_label' => $periodStart->copy()->locale('nl')->isoFormat('MMMM YYYY'),
                    'response'     => [
                        'month' => $periodStart->format('Y-m'),
                    ],
                ],
            ];
        }

        $week = (int) $request->query('week', now()->isoWeek());
        $year = (int) $request->query('year', now()->year);

        $periodStart = Carbon::now()->setISODate($year, $week)->startOfWeek(CarbonInterface::MONDAY);
        $periodEnd = $periodStart->copy()->endOfWeek(CarbonInterface::SUNDAY);

        $days = collect(range(0, 6))
            ->map(function (int $offset) use ($periodStart) {
                $date = $periodStart->copy()->addDays($offset)->locale('nl');

                return [
                    'date'       => $date->toDateString(),
                    'label'      => $date->isoFormat('dd D'),
                    'is_weekend' => $date->isWeekend(),
                ];
            })
            ->values();

        return [
            $periodStart,
            $periodEnd,
            $days,
            [
                'period_label' => $this->weekLabel($periodStart, $periodEnd),
                'response'     => [
                    'week' => $periodStart->isoWeek(),
                    'year' => $periodStart->isoWeekYear(),
                ],
            ],
        ];
    }

    private function departmentForPipeline(int $pipelineId): ?Departments
    {
        return match ($pipelineId) {
            PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value => Departments::PRIVATESCAN,
            PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value      => Departments::HERNIA,
            default                                                    => null,
        };
    }

    /**
     * @return list<string>
     */
    private function arrayQuery(Request $request, string $key): array
    {
        $value = $request->query($key, []);

        if (is_string($value)) {
            return array_values(array_filter(explode(',', $value), fn (string $item) => $item !== ''));
        }

        return is_array($value) ? array_values($value) : [];
    }

    private function weekLabel(Carbon $weekStart, Carbon $weekEnd): string
    {
        $start = $weekStart->copy()->locale('nl');
        $end = $weekEnd->copy()->locale('nl');

        return sprintf(
            'Week %d — %s t/m %s',
            $weekStart->isoWeek(),
            $start->isoFormat('D MMM'),
            $end->isoFormat('D MMM YYYY')
        );
    }
}
