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
            ->whereBetween('first_examination_at', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with('salesLead')
            ->orderBy('first_examination_at')
            ->orderBy('first_examination_time')
            ->get(['id', 'order_number', 'title', 'first_examination_at', 'first_examination_time', 'pipeline_stage_id', 'sales_lead_id', 'user_id']);

        $countByDay = $orders
            ->groupBy(fn (Order $o) => $o->first_examination_at->toDateString())
            ->map(fn ($group) => $group->count());

        $chartData = $days->map(fn (array $day) => $countByDay->get($day['date'], 0))->values()->all();

        $rows = $orders->map(fn (Order $o) => [
            'id'                  => $o->id,
            'url'                 => route('admin.orders.view', $o->id),
            'onderzoeksdatum'     => $o->first_examination_at->format('d-m-Y'),
            'naam'                => trim(($o->order_number ?? '') . ' ' . ($o->salesLead?->name ?? $o->title ?? "Order #{$o->id}")),
            'datum_1e_onderzoek'  => $o->first_examination_at->format('d-m-Y') . ($o->first_examination_time ? ' ' . $o->first_examination_time : ''),
            'wf_status'           => $stageLabelMap->get($o->pipeline_stage_id, '—'),
        ])->values()->all();

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
