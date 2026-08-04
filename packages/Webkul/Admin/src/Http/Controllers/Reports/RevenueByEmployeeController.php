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
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\User\Models\User;

class RevenueByEmployeeController extends Controller
{
    private const GROUPS = [
        'option'     => ['label' => 'Option',         'color' => '#3CC3DF'],
        'nearly_won' => ['label' => 'Bijna gewonnen', 'color' => '#FFD166'],
        'won'        => ['label' => 'Gewonnen',        'color' => '#6BCB77'],
        'lost'       => ['label' => 'Verloren',        'color' => '#FF928A'],
    ];

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
            'departments'   => array_map(
                fn (Departments $department) => [
                    'id'    => $department->key(),
                    'label' => $department->value,
                ],
                self::DEPARTMENTS
            ),
        ]);
    }

    public function filterOptions(): JsonResponse
    {
        $departments = collect(self::DEPARTMENTS)
            ->map(fn (Departments $department) => [
                'id'    => $department->key(),
                'label' => $department->value,
            ])
            ->values();

        $groups = collect(self::GROUPS)
            ->map(fn (array $group, string $id) => [
                'id'    => $id,
                'label' => $group['label'],
                'color' => $group['color'],
            ])
            ->values();

        return response()->json([
            'groups'      => $groups,
            'departments' => $departments,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $period = $request->query('period', 'week') === 'month' ? 'month' : 'week';

        [$periodStart, $periodEnd, $days, $periodMeta] = $this->resolvePeriod($request, $period);

        $selectedGroups = $this->arrayQuery($request, 'groups');
        $selectedGroups = array_values(array_intersect($selectedGroups, array_keys(self::GROUPS)));

        if (empty($selectedGroups)) {
            $selectedGroups = ['option', 'nearly_won', 'won'];
        }

        $departmentKeys = array_map(fn (Departments $department) => $department->key(), self::DEPARTMENTS);

        $selectedDepartments = $request->has('departments')
            ? array_values(array_intersect($this->arrayQuery($request, 'departments'), $departmentKeys))
            : $departmentKeys;

        if (empty($selectedDepartments)) {
            $selectedDepartments = $departmentKeys;
        }

        $allOrderStages = collect(PipelineStage::cases())
            ->filter(fn (PipelineStage $s) => $s->isOrder())
            ->filter(fn (PipelineStage $s) => in_array($this->departmentForPipeline($s->pipeline())?->key(), $selectedDepartments, true))
            ->filter(fn (PipelineStage $s) => $s->statusCategory() !== null);

        // Always load lost for netto; selected groups control chart visibility.
        $fetchGroups = array_values(array_unique(array_merge($selectedGroups, ['lost'])));

        $groupStageMap = [];
        foreach ($fetchGroups as $group) {
            $groupStageMap[$group] = $allOrderStages
                ->filter(fn (PipelineStage $s) => $s->statusCategory()?->value === $group)
                ->map(fn (PipelineStage $s) => $s->id())
                ->values()
                ->all();
        }

        $stageIds = array_values(array_unique(array_merge([], ...array_values($groupStageMap))));

        $stageToGroup = [];
        foreach ($groupStageMap as $group => $ids) {
            foreach ($ids as $id) {
                $stageToGroup[$id] = $group;
            }
        }

        $stageLabelMap = collect(PipelineStage::cases())
            ->filter(fn (PipelineStage $s) => $s->isOrder())
            ->mapWithKeys(fn (PipelineStage $s) => [$s->id() => $s->label()]);

        $lostStageIds = $groupStageMap['lost'] ?? [];
        $selectedStageIds = collect($selectedGroups)
            ->flatMap(fn (string $group) => $groupStageMap[$group] ?? [])
            ->unique()
            ->values()
            ->all();

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
            ->whereIn('id', $ordersByUser->keys()->map(fn ($id) => (int) $id)->values())
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        $dayIndex = $days->pluck('date')->flip();
        $dayCount = $days->count();

        $datasets = [];
        $employees = [];

        foreach ($ordersByUser as $userId => $userOrders) {
            $user = $users->get((int) $userId);
            $color = self::PALETTE[count($datasets) % count(self::PALETTE)];
            $data = array_fill(0, $dayCount, 0.0);

            // Chart bars follow selected groups only (lost series/visibility via filter).
            foreach ($userOrders as $order) {
                if (! in_array($order->pipeline_stage_id, $selectedStageIds, true)) {
                    continue;
                }

                $index = $dayIndex->get($order->created_at->toDateString());

                if ($index !== null) {
                    $data[$index] = round($data[$index] + (float) $order->total_price, 2);
                }
            }

            $name = $user?->name ?: 'Onbekende medewerker';
            $verloren = round(
                $userOrders
                    ->filter(fn ($o) => in_array($o->pipeline_stage_id, $lostStageIds, true))
                    ->sum(fn ($o) => (float) $o->total_price),
                2
            );
            $selectedNonLostTotal = round(
                $userOrders
                    ->filter(function ($o) use ($stageToGroup, $selectedGroups) {
                        $group = $stageToGroup[$o->pipeline_stage_id] ?? null;

                        return $group !== null && $group !== 'lost' && in_array($group, $selectedGroups, true);
                    })
                    ->sum(fn ($o) => (float) $o->total_price),
                2
            );
            $bruto = round($selectedNonLostTotal + $verloren, 2);

            $datasets[] = [
                'label'           => $name,
                'data'            => $data,
                'backgroundColor' => $color,
                'borderRadius'    => 4,
                'user_id'         => (int) $userId,
            ];

            $employees[] = [
                'user_id'  => (int) $userId,
                'name'     => $name,
                'color'    => $color,
                'bruto'    => $bruto,
                'verloren' => $verloren,
                'inkoop'   => round(
                    $userOrders->sum(fn ($o) => $o->totalPurchasePrice()),
                    2
                ),
                'netto'    => round($bruto - $verloren, 2),
                'orders'   => $userOrders
                    ->map(fn ($o) => [
                        'id'           => $o->id,
                        'label'        => $o->order_number ?: $o->title ?: "Order #{$o->id}",
                        'url'          => route('admin.orders.view', $o->id),
                        'created_at'   => $o->created_at->toDateString(),
                        'stage'        => $stageLabelMap->get($o->pipeline_stage_id, '—'),
                        'group'        => $stageToGroup[$o->pipeline_stage_id] ?? null,
                        'is_lost'      => in_array($o->pipeline_stage_id, $lostStageIds, true),
                        'total_price'  => round((float) $o->total_price, 2),
                        'inkoop_price' => $o->totalPurchasePrice(),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        usort($employees, fn (array $a, array $b) => $b['bruto'] <=> $a['bruto']);

        return response()->json(array_merge([
            'period'          => $period,
            'period_label'    => $periodMeta['period_label'],
            'days'            => $days,
            'datasets'        => $datasets,
            'employees'       => $employees,
            'selected_groups' => $selectedGroups,
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
