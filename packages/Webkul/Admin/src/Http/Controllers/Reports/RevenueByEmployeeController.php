<?php

namespace Webkul\Admin\Http\Controllers\Reports;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\User\Models\User;

class RevenueByEmployeeController extends Controller
{
    private const DEPARTMENTS = [
        'privatescan' => 'Privatescan',
        'hernia'      => 'Hernia',
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
        return view('admin::reports.revenue-by-employee.index', [
            'initialWeek' => $request->integer('week', now()->isoWeek()),
            'initialYear' => $request->integer('year', now()->year),
        ]);
    }

    public function filterOptions(): JsonResponse
    {
        $stages = collect(PipelineStage::cases())
            ->filter(fn (PipelineStage $stage) => $stage->isOrder())
            ->map(fn (PipelineStage $stage) => [
                'id'         => $stage->id(),
                'label'      => $stage->label(),
                'department' => $this->departmentForPipeline($stage->pipeline()),
                'is_lost'    => $stage->isLost(),
            ])
            ->filter(fn (array $stage) => $stage['department'] !== null)
            ->values();

        $departments = collect(self::DEPARTMENTS)
            ->map(fn (string $label, string $id) => compact('id', 'label'))
            ->values();

        return response()->json([
            'stages'      => $stages,
            'departments' => $departments,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $week = (int) $request->query('week', now()->isoWeek());
        $year = (int) $request->query('year', now()->year);

        $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(CarbonInterface::SUNDAY);

        $selectedDepartments = $request->has('departments')
            ? array_values(array_intersect($this->arrayQuery($request, 'departments'), array_keys(self::DEPARTMENTS)))
            : array_keys(self::DEPARTMENTS);

        $stageIds = collect(PipelineStage::cases())
            ->filter(fn (PipelineStage $stage) => $stage->isOrder())
            ->filter(fn (PipelineStage $stage) => in_array($this->departmentForPipeline($stage->pipeline()), $selectedDepartments, true))
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

        $days = collect(range(0, 6))
            ->map(function (int $offset) use ($weekStart) {
                $date = $weekStart->copy()->addDays($offset)->locale('nl');

                return [
                    'date'       => $date->toDateString(),
                    'label'      => $date->isoFormat('dd D'),
                    'is_weekend' => $date->isWeekend(),
                ];
            })
            ->values();

        $stageLabelMap = collect(PipelineStage::cases())
            ->filter(fn (PipelineStage $s) => $s->isOrder())
            ->mapWithKeys(fn (PipelineStage $s) => [$s->id() => $s->label()]);

        $rows = empty($stageIds)
            ? collect()
            : Order::query()
                ->whereBetween('created_at', [$weekStart, $weekEnd])
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
                ->whereBetween('created_at', [$weekStart, $weekEnd])
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

        $groupedRows = $rows->groupBy('user_id');

        $datasets = [];
        $employees = [];

        foreach ($groupedRows as $userId => $userRows) {
            $user = $users->get((int) $userId);
            $color = self::PALETTE[count($datasets) % count(self::PALETTE)];
            $data = array_fill(0, 7, 0.0);

            foreach ($userRows as $row) {
                $index = $dayIndex->get($row->day);

                if ($index !== null) {
                    $data[$index] = round((float) $row->total, 2);
                }
            }

            $name = $user?->name ?: 'Onbekende medewerker';

            $datasets[] = [
                'label'           => $name,
                'data'            => $data,
                'backgroundColor' => $color,
                'borderRadius'    => 4,
                'user_id'         => (int) $userId,
            ];

            $employees[] = [
                'user_id'    => (int) $userId,
                'name'       => $name,
                'color'      => $color,
                'week_total' => round(array_sum($data), 2),
                'week_inkoop' => round(
                    ($ordersByUser->get($userId) ?? collect())
                        ->sum(fn ($o) => $o->totalPurchasePrice()),
                    2
                ),
                'orders'     => ($ordersByUser->get($userId) ?? collect())
                    ->map(fn ($o) => [
                        'id'          => $o->id,
                        'label'       => $o->order_number ?: $o->title ?: "Order #{$o->id}",
                        'url'         => route('admin.orders.view', $o->id),
                        'created_at'  => $o->created_at->toDateString(),
                        'stage'       => $stageLabelMap->get($o->pipeline_stage_id, '—'),
                        'total_price' => round((float) $o->total_price, 2),
                        'inkoop_price' => $o->totalPurchasePrice(),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        usort($employees, fn (array $a, array $b) => $b['week_total'] <=> $a['week_total']);

        return response()->json([
            'week'       => $weekStart->isoWeek(),
            'year'       => $weekStart->isoWeekYear(),
            'week_label' => $this->weekLabel($weekStart, $weekEnd),
            'days'       => $days,
            'datasets'   => $datasets,
            'employees'  => $employees,
        ]);
    }

    private function departmentForPipeline(int $pipelineId): ?string
    {
        return match ($pipelineId) {
            PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value => 'privatescan',
            PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value      => 'hernia',
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
