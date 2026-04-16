<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AfbDispatchStatus;
use App\Enums\PipelineStage;
use App\Http\Controllers\Controller;
use App\Models\AfbPersonDocument;
use App\Models\Order;
use App\Support\GvlFormLink;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                    })->with(['product', 'person', 'resourceOrderItems']);
                },
                'stage',
                'user',
                'afbPersonDocuments.dispatch.clinic',
                'afbPersonDocuments.dispatch.clinicDepartment',
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

            $afbByPerson = $order->latestSuccessfulAfbDocuments()->groupBy('person_id');

            return $order->orderItems
                ->groupBy('person_id')
                ->map(function ($items, $personId) use ($orderData, $salesLeadData, $orderUrl, $anamnesisRecords, $afbByPerson) {
                    $person = $items->first()->person;
                    $gvlFormLink = $anamnesisRecords
                        ->firstWhere('person_id', (int) $personId)
                        ?->gvl_form_link;

                    $afbDocuments = ($afbByPerson->get((int) $personId) ?? collect())
                        ->map(fn ($doc) => [
                            'url'   => route('admin.clinic-guide.afb-pdf.view', ['personDocumentId' => $doc->id]),
                            'label' => implode(' - ', array_filter([
                                $doc->dispatch?->clinic?->name,
                                $doc->dispatch?->clinicDepartment?->name,
                            ])),
                        ])
                        ->values()
                        ->all();

                    return [
                        'order'          => $orderData,
                        'sales_lead'     => $salesLeadData,
                        'patient'        => $person ? [
                            'id'            => $person->id,
                            'name'          => $person->name,
                            'date_of_birth' => $person->date_of_birth?->format('d-m-Y'),
                            'age'           => $person->age ?? null,
                            'gender'        => $person->gender ?? null,
                            'phones'        => $person->phones ?? [],
                            'emails'        => $person->emails ?? [],
                        ] : null,
                        'gvl_form_link'  => GvlFormLink::adminOpenUrlForPerson($gvlFormLink, $person),
                        'afb_documents'  => $afbDocuments,
                        'order_items'    => $items->map(fn ($item) => [
                            'product_name' => $item->product?->name,
                            'person_name'  => $item->person?->name,
                            'quantity'     => $item->quantity,
                            'start_time'   => $item->resourceOrderItems->sortBy('from')->first()?->from?->format('H:i'),
                        ]),
                        'order_url'      => $orderUrl,
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

    /**
     * Serve stored AFB PDF inline so the browser opens it in the built-in PDF viewer (same file as e-mail attachment).
     */
    public function viewAfbPdf(int $personDocumentId): Response|StreamedResponse
    {
        $document = AfbPersonDocument::query()
            ->whereHas('dispatch', fn ($q) => $q->where('status', AfbDispatchStatus::SUCCESS->value))
            ->findOrFail($personDocumentId);

        $disk = Storage::disk('local');

        if (! $disk->exists($document->file_path)) {
            abort(404, 'AFB-document niet gevonden.');
        }

        return $disk->response($document->file_path, $document->file_name, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$document->file_name.'"',
        ]);
    }
}
