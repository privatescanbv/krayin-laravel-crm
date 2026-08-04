<?php

namespace Webkul\Admin\Http\Controllers\Reports;

use App\Enums\Departments;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    /**
     * Afdelingen in weergavevolgorde. De filterwaarde op de wire is `Departments::key()`.
     *
     * @var list<Departments>
     */
    private const DEPARTMENTS = [
        Departments::PRIVATESCAN,
        Departments::HERNIA,
    ];

    public function index(Request $request): View
    {
        return view('admin::reports.revenue-by-month.index', [
            'initialFrom' => $request->input('from', now()->subMonths(11)->format('Y-m')),
            'initialTo'   => $request->input('to', now()->format('Y-m')),
            'departments' => array_map(
                fn (Departments $department) => [
                    'id'    => $department->key(),
                    'label' => $department->value,
                ],
                self::DEPARTMENTS
            ),
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

        $departmentKeys = array_map(fn (Departments $department) => $department->key(), self::DEPARTMENTS);

        $selectedDepartments = $this->arrayQuery($request, 'departments');
        $selectedDepartments = array_values(array_intersect($selectedDepartments, $departmentKeys));

        if (empty($selectedDepartments)) {
            $selectedDepartments = $departmentKeys;
        }

        // Build all order stages from enum, filtered by department
        $allOrderStages = collect(PipelineStage::cases())
            ->filter(fn (PipelineStage $s) => $s->isOrder())
            ->filter(fn (PipelineStage $s) => in_array($this->departmentForPipeline($s->pipeline())?->key(), $selectedDepartments, true))
            ->filter(fn (PipelineStage $s) => $s->statusCategory() !== null);

        // Always load lost for netto; selected groups control chart + column visibility.
        $fetchGroups = array_values(array_unique(array_merge($selectedGroups, ['lost'])));

        // Map group key -> stage IDs
        $groupStageMap = [];
        foreach ($fetchGroups as $group) {
            $groupStageMap[$group] = $allOrderStages
                ->filter(fn (PipelineStage $s) => $s->statusCategory()?->value === $group)
                ->map(fn (PipelineStage $s) => $s->id())
                ->values()
                ->all();
        }

        $allStageIds = array_values(array_unique(array_merge([], ...array_values($groupStageMap))));

        $stageToGroup = [];

        foreach ($groupStageMap as $group => $ids) {
            foreach ($ids as $id) {
                $stageToGroup[$id] = $group;
            }
        }

        $stageLabelMap = collect(PipelineStage::cases())
            ->filter(fn (PipelineStage $s) => $s->isOrder())
            ->mapWithKeys(fn (PipelineStage $s) => [$s->id() => $s->label()]);

        $months = [];
        $cursor = $periodStart->copy()->startOfMonth();

        while ($cursor->lte($periodEnd)) {
            $months[] = [
                'key'   => $cursor->format('Y-m'),
                'label' => $cursor->locale('nl')->isoFormat('MMM \'YY'),
            ];

            $cursor->addMonth();
        }

        $orders = empty($allStageIds)
            ? collect()
            : Order::query()
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->whereIn('pipeline_stage_id', $allStageIds)
                ->with([
                    'orderItems.purchasePrice',
                    'orderItems.product.partnerProducts.purchasePrice',
                ])
                ->orderBy('created_at')
                ->get(['id', 'order_number', 'title', 'total_price', 'created_at', 'pipeline_stage_id']);

        $monthlyRevenue = [];
        $inkooByMonth = [];
        $ordersByMonthGroup = [];

        foreach ($months as $m) {
            $monthlyRevenue[$m['key']] = array_fill_keys(array_keys(self::GROUPS), 0.0);
            $inkooByMonth[$m['key']] = 0.0;
            $ordersByMonthGroup[$m['key']] = array_fill_keys(array_keys(self::GROUPS), []);
        }

        foreach ($orders as $order) {
            $key = $order->created_at->format('Y-m');
            $group = $stageToGroup[$order->pipeline_stage_id] ?? null;

            if (! $group || ! isset($monthlyRevenue[$key])) {
                continue;
            }

            $inkoop = $order->totalPurchasePrice();
            $monthlyRevenue[$key][$group] += (float) $order->total_price;
            $inkooByMonth[$key] += $inkoop;

            $ordersByMonthGroup[$key][$group][] = [
                'id'           => $order->id,
                'label'        => $order->order_number ?: $order->title ?: "Order #{$order->id}",
                'url'          => route('admin.orders.view', $order->id),
                'created_at'   => $order->created_at->toDateString(),
                'stage'        => $stageLabelMap->get($order->pipeline_stage_id, '—'),
                'group'        => $group,
                'total_price'  => round((float) $order->total_price, 2),
                'inkoop_price' => round($inkoop, 2),
            ];
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

        $monthsData = array_map(function ($m) use ($monthlyRevenue, $inkooByMonth, $selectedGroups, $ordersByMonthGroup) {
            $row = $monthlyRevenue[$m['key']];
            $verloren = $row['lost'] ?? 0.0;
            $selectedNonLost = array_values(array_filter($selectedGroups, fn (string $g) => $g !== 'lost'));
            $nonLostTotal = array_sum(array_map(fn ($g) => $row[$g] ?? 0, $selectedNonLost));
            $bruto = $nonLostTotal + $verloren;
            $groupOrders = $ordersByMonthGroup[$m['key']];

            // Always expose lost orders; other groups only when selected.
            $ordersByGroup = [
                'lost' => $groupOrders['lost'] ?? [],
            ];
            foreach ($selectedNonLost as $group) {
                $ordersByGroup[$group] = $groupOrders[$group] ?? [];
            }

            /** @var Collection<int, array<string, mixed>> $allOrders */
            $allOrders = collect($ordersByGroup)
                ->flatten(1)
                ->sortBy('created_at')
                ->values();

            return array_merge($m, [
                'option'     => round($row['option'] ?? 0, 2),
                'nearly_won' => round($row['nearly_won'] ?? 0, 2),
                'won'        => round($row['won'] ?? 0, 2),
                'verloren'   => round($verloren, 2),
                'inkoop'     => round($inkooByMonth[$m['key']] ?? 0, 2),
                'bruto'      => round($bruto, 2),
                'netto'      => round($bruto - $verloren, 2),
                'orders'     => array_merge($ordersByGroup, [
                    'all' => $allOrders->all(),
                ]),
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

    private function departmentForPipeline(int $pipelineId): ?Departments
    {
        return match ($pipelineId) {
            PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value => Departments::PRIVATESCAN,
            PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value      => Departments::HERNIA,
            default                                                    => null,
        };
    }
}
