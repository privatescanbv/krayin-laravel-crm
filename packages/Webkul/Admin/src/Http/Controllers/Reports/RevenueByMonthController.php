<?php

namespace Webkul\Admin\Http\Controllers\Reports;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class RevenueByMonthController extends Controller
{
    private const GROUPS = [
        'option'     => ['label' => 'Option',         'color' => '#3CC3DF'],
        'nearly_won' => ['label' => 'Bijna gewonnen', 'color' => '#FFD166'],
        'won'        => ['label' => 'Gewonnen',        'color' => '#6BCB77'],
        'lost'       => ['label' => 'Verloren',        'color' => '#FF928A'],
    ];

    private const DEPARTMENT_LABELS = [
        'privatescan' => 'Privatescan',
        'hernia'      => 'Hernia',
    ];

    public function index(Request $request): View
    {
        return view('admin::reports.revenue-by-month.index', [
            'initialFrom' => $request->input('from', now()->subMonths(11)->format('Y-m')),
            'initialTo'   => $request->input('to', now()->format('Y-m')),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->subMonths(11)->format('Y-m'));
        $to = $request->input('to', now()->format('Y-m'));

        if ($from > $to) {
            $to = $from;
        }

        $periodStart = Carbon::parse("{$from}-01")->startOfMonth();
        $periodEnd = Carbon::parse("{$to}-01")->endOfMonth();

        $selectedGroups = $this->arrayQuery($request, 'groups');
        $selectedGroups = array_values(array_intersect($selectedGroups, array_keys(self::GROUPS)));

        if (empty($selectedGroups)) {
            $selectedGroups = ['option', 'nearly_won', 'won'];
        }

        $selectedDepartments = $this->arrayQuery($request, 'departments');
        $selectedDepartments = array_values(array_intersect($selectedDepartments, array_keys(self::DEPARTMENT_LABELS)));

        if (empty($selectedDepartments)) {
            $selectedDepartments = array_keys(self::DEPARTMENT_LABELS);
        }

        // Build all order stages from enum, filtered by department
        $allOrderStages = collect(PipelineStage::cases())
            ->filter(fn (PipelineStage $s) => $s->isOrder())
            ->filter(fn (PipelineStage $s) => in_array($this->departmentForPipeline($s->pipeline()), $selectedDepartments, true))
            ->filter(fn (PipelineStage $s) => $s->statusCategory() !== null);

        // Map group key -> stage IDs (only for selected groups)
        $groupStageMap = [];
        foreach ($selectedGroups as $group) {
            $groupStageMap[$group] = $allOrderStages
                ->filter(fn (PipelineStage $s) => $s->statusCategory()?->value === $group)
                ->map(fn (PipelineStage $s) => $s->id())
                ->values()
                ->all();
        }

        $allStageIds = array_unique(array_merge(...array_values($groupStageMap)));

        $stageToGroup = [];

        foreach ($groupStageMap as $group => $ids) {
            foreach ($ids as $id) {
                $stageToGroup[$id] = $group;
            }
        }

        $months = [];
        $cursor = $periodStart->copy()->startOfMonth();

        while ($cursor->lte($periodEnd)) {
            $months[] = [
                'key'   => $cursor->format('Y-m'),
                'label' => $cursor->locale('nl')->isoFormat('MMM \'YY'),
            ];

            $cursor->addMonth();
        }

        $rows = empty($allStageIds)
            ? collect()
            : Order::query()
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->whereIn('pipeline_stage_id', $allStageIds)
                ->select([
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    'pipeline_stage_id',
                    DB::raw('SUM(total_price) as total'),
                ])
                ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'), 'pipeline_stage_id')
                ->get();

        $inkooByMonth = [];

        if (! empty($allStageIds)) {
            Order::query()
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->whereIn('pipeline_stage_id', $allStageIds)
                ->with(['orderItems.purchasePrice', 'orderItems.product.partnerProducts.purchasePrice'])
                ->get(['id', 'created_at', 'pipeline_stage_id'])
                ->each(function ($order) use (&$inkooByMonth) {
                    $key = $order->created_at->format('Y-m');
                    $inkooByMonth[$key] = ($inkooByMonth[$key] ?? 0.0) + $order->totalPurchasePrice();
                });
        }

        $monthlyRevenue = [];

        foreach ($months as $m) {
            $monthlyRevenue[$m['key']] = array_fill_keys(array_keys(self::GROUPS), 0.0);
        }

        foreach ($rows as $row) {
            $key = sprintf('%04d-%02d', $row->year, $row->month);
            $group = $stageToGroup[$row->pipeline_stage_id] ?? null;

            if ($group && isset($monthlyRevenue[$key])) {
                $monthlyRevenue[$key][$group] += (float) $row->total;
            }
        }

        $datasets = [];

        foreach ($selectedGroups as $group) {
            $datasets[] = [
                'label'           => self::GROUPS[$group]['label'],
                'data'            => array_map(
                    fn ($m) => round($monthlyRevenue[$m['key']][$group] ?? 0, 2),
                    $months
                ),
                'backgroundColor' => self::GROUPS[$group]['color'],
                'borderRadius'    => 4,
                'group'           => $group,
            ];
        }

        $monthsData = array_map(function ($m) use ($monthlyRevenue, $inkooByMonth, $selectedGroups) {
            $row = $monthlyRevenue[$m['key']];
            $groupTotal = array_sum(array_map(fn ($g) => $row[$g] ?? 0, $selectedGroups));

            return array_merge($m, [
                'option'     => round($row['option'] ?? 0, 2),
                'nearly_won' => round($row['nearly_won'] ?? 0, 2),
                'won'        => round($row['won'] ?? 0, 2),
                'lost'       => round($row['lost'] ?? 0, 2),
                'inkoop'     => round($inkooByMonth[$m['key']] ?? 0, 2),
                'total'      => round($groupTotal, 2),
            ]);
        }, $months);

        $periodLabel = sprintf(
            '%s t/m %s',
            $periodStart->locale('nl')->isoFormat('MMM YYYY'),
            $periodEnd->locale('nl')->isoFormat('MMM YYYY')
        );

        return response()->json([
            'period_label'    => $periodLabel,
            'months'          => $months,
            'datasets'        => $datasets,
            'months_data'     => $monthsData,
            'selected_groups' => $selectedGroups,
        ]);
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

    private function departmentForPipeline(int $pipelineId): ?string
    {
        return match ($pipelineId) {
            PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value => 'privatescan',
            PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value      => 'hernia',
            default                                                    => null,
        };
    }
}
