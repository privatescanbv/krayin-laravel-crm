<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PipelineStage;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicGuideController extends Controller
{
    public function index()
    {
        return view('adminc::clinic_guide.index');
    }

    public function get(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
        ]);

        $date = $request->input('date', now()->format('Y-m-d'));
        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();

        $orders = Order::query()
            ->whereNotNull('first_examination_at')
            ->whereBetween('first_examination_at', [$startOfDay, $endOfDay])
            ->whereIn('pipeline_stage_id', PipelineStage::getOrderStagesIdsForClinicGuide())
            ->with([
                'salesLead.persons',
                'salesLead.stage',
                'salesLead.lead',
                'salesLead.contactPerson',
                'orderItems.product',
                'orderItems.person',
                'stage',
                'user',
            ])
            ->orderBy('first_examination_at', 'asc')
            ->get();

        $data = $orders->map(function (Order $order) {
            $salesLead = $order->salesLead;
            $patient = $salesLead?->getContactPersonOrFirstPerson();

            return [
                'order' => [
                    'id'                   => $order->id,
                    'title'                => $order->title,
                    'first_examination_at' => $order->first_examination_at?->toIso8601String(),
                    'time'                 => $order->first_examination_at?->format('H:i'),
                    'total_price'          => $order->total_price,
                    'stage'                => $order->stage ? [
                        'name'    => $order->stage->name,
                        'is_won'  => (bool) $order->stage->is_won,
                        'is_lost' => (bool) $order->stage->is_lost,
                    ] : null,
                ],
                'sales_lead' => $salesLead ? [
                    'id'    => $salesLead->id,
                    'name'  => $salesLead->name,
                    'stage' => $salesLead->stage ? [
                        'name'    => $salesLead->stage->name,
                        'is_won'  => (bool) $salesLead->stage->is_won,
                        'is_lost' => (bool) $salesLead->stage->is_lost,
                    ] : null,
                ] : null,
                'patient' => $patient ? [
                    'id'            => $patient->id,
                    'name'          => $patient->name,
                    'date_of_birth' => $patient->date_of_birth?->format('d-m-Y'),
                    'age'           => $patient->age ?? null,
                    'gender'        => $patient->gender ?? null,
                    'phones'        => $patient->phones ?? [],
                    'emails'        => $patient->emails ?? [],
                ] : null,
                'order_items' => $order->orderItems->map(fn ($item) => [
                    'product_name' => $item->product?->name,
                    'person_name'  => $item->person?->name,
                    'quantity'     => $item->quantity,
                ]),
                'sales_lead_url' => $salesLead
                    ? route('admin.sales-leads.view', $salesLead->id)
                    : null,
            ];
        });

        return response()->json([
            'date'   => $date,
            'count'  => $data->count(),
            'orders' => $data,
        ]);
    }
}
