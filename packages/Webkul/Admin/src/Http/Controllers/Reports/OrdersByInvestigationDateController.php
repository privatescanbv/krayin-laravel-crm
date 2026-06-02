<?php

namespace Webkul\Admin\Http\Controllers\Reports;

use App\Enums\PipelineStage;
use App\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class OrdersByInvestigationDateController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin::reports.orders-by-investigation-date.index', [
            'initialWeek' => $request->integer('week', now()->isoWeek()),
            'initialYear' => $request->integer('year', now()->year),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $week = (int) $request->query('week', now()->isoWeek());
        $year = (int) $request->query('year', now()->year);

        $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd   = $weekStart->copy()->endOfWeek(CarbonInterface::SUNDAY);

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

        $orders = Order::query()
            ->where(function ($q) use ($weekStart, $weekEnd) {
                $q->whereBetween('first_examination_at', [$weekStart->toDateString(), $weekEnd->toDateString()])
                  ->orWhereHas('orderItems', function ($q) use ($weekStart, $weekEnd) {
                      $q->where('status', '!=', 'lost')
                        ->whereHas('resourceOrderItems', function ($q) use ($weekStart, $weekEnd) {
                            $q->whereBetween('from', [
                                $weekStart->copy()->startOfDay(),
                                $weekEnd->copy()->endOfDay(),
                            ]);
                        });
                  });
            })
            ->with(['salesLead', 'orderItems.resourceOrderItems'])
            ->get(['id', 'order_number', 'title', 'first_examination_at', 'first_examination_time', 'pipeline_stage_id', 'sales_lead_id', 'user_id']);

        // Expand each order to one row per clinic guide day that falls within this week.
        $rows = $orders
            ->flatMap(function (Order $o) use ($weekStart, $weekEnd, $stageLabelMap) {
                $naam = trim(($o->order_number ?? '') . ' ' . ($o->salesLead?->name ?? $o->title ?? "Order #{$o->id}"));

                return $o->clinicGuideDays()
                    ->filter(fn (array $entry) => $entry['date']->between($weekStart, $weekEnd))
                    ->map(fn (array $entry) => [
                        'id'                 => $o->id,
                        'url'                => route('admin.orders.view', $o->id),
                        'onderzoeksdatum'    => $entry['date']->format('d-m-Y'),
                        'naam'               => $naam,
                        'datum_1e_onderzoek' => $entry['at']->format('d-m-Y H:i'),
                        'wf_status'          => $stageLabelMap->get($o->pipeline_stage_id, '—'),
                        '_sort_ts'           => $entry['at']->timestamp,
                        '_date'              => $entry['date']->toDateString(),
                    ]);
            })
            ->sortBy('_sort_ts')
            ->values()
            ->map(fn (array $row) => array_diff_key($row, ['_sort_ts' => 0, '_date' => 0]))
            ->values()
            ->all();

        $countByDay = $orders
            ->flatMap(fn (Order $o) => $o->clinicGuideDays()
                ->filter(fn (array $entry) => $entry['date']->between($weekStart, $weekEnd))
                ->map(fn (array $entry) => $entry['date']->toDateString())
            )
            ->countBy()
            ->all();

        $chartData = $days->map(fn (array $day) => $countByDay[$day['date']] ?? 0)->values()->all();

        return response()->json([
            'week'       => $weekStart->isoWeek(),
            'year'       => $weekStart->isoWeekYear(),
            'week_label' => $this->weekLabel($weekStart, $weekEnd),
            'days'       => $days,
            'chart_data' => $chartData,
            'rows'       => $rows,
        ]);
    }

    private function weekLabel(Carbon $weekStart, Carbon $weekEnd): string
    {
        return sprintf(
            'Week %d — %s t/m %s',
            $weekStart->isoWeek(),
            $weekStart->copy()->locale('nl')->isoFormat('D MMM'),
            $weekEnd->copy()->locale('nl')->isoFormat('D MMM YYYY')
        );
    }
}
