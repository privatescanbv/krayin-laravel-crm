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
                'orderItems' => function ($query) {
                    $query->whereHas('product', function ($q) {
                        $q->whereHas('partnerProducts', function ($q) {
                            $q->whereHas('clinics');
                        });
                    })->with(['product', 'person']);
                },
                'stage',
                'user',
            ])
            ->orderBy('first_examination_at', 'asc')
            ->get();

        $data = $orders->flatMap(function (Order $order) {
            $salesLead = $order->salesLead;

            $orderData = [
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
            ];

            $salesLeadData = $salesLead ? [
                'id'    => $salesLead->id,
                'name'  => $salesLead->name,
                'stage' => $salesLead->stage ? [
                    'name'    => $salesLead->stage->name,
                    'is_won'  => (bool) $salesLead->stage->is_won,
                    'is_lost' => (bool) $salesLead->stage->is_lost,
                ] : null,
            ] : null;

            $orderUrl = route('admin.orders.view', $order->id);
            $anamnesisRecords = $salesLead ? $salesLead->anamnesis : collect();

            return $order->orderItems
                ->groupBy('person_id')
                ->map(function ($items, $personId) use ($orderData, $salesLeadData, $orderUrl, $anamnesisRecords) {
                    $person = $items->first()->person;
                    $gvlFormLink = $anamnesisRecords
                        ->firstWhere('person_id', (int) $personId)
                        ?->gvl_form_link;

                    return [
                        'order'         => $orderData,
                        'sales_lead'    => $salesLeadData,
                        'patient'       => $person ? [
                            'id'            => $person->id,
                            'name'          => $person->name,
                            'date_of_birth' => $person->date_of_birth?->format('d-m-Y'),
                            'age'           => $person->age ?? null,
                            'gender'        => $person->gender ?? null,
                            'phones'        => $person->phones ?? [],
                            'emails'        => $person->emails ?? [],
                        ] : null,
                        'gvl_form_link' => $gvlFormLink,
                        'order_items'   => $items->map(fn ($item) => [
                            'product_name' => $item->product?->name,
                            'person_name'  => $item->person?->name,
                            'quantity'     => $item->quantity,
                        ]),
                        'order_url'     => $orderUrl,
                    ];
                })
                ->values();
        });

        return response()->json([
            'date'   => $date,
            'count'  => $data->count(),
            'orders' => $data->values(),
        ]);
    }
}
