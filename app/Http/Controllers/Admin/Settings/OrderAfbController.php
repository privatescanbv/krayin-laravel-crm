<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Enums\AfbDispatchStatus;
use App\Enums\AfbDispatchType;
use App\Models\AfbDispatch;
use App\Models\AfbPersonDocument;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\Afb\AfbDispatchService;
use App\Services\Afb\AfbDocumentGenerator;
use App\Services\Anamnesis\AnamnesisGvlFormResolver;
use App\Services\FormService;
use App\Services\Mail\CrmMailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * AFB (aanvraagformulier behandeling) dispatch endpoints for orders:
 * automatic dispatch, the manual send screen and attachment previews/downloads.
 */
class OrderAfbController extends Controller
{
    public function __construct(
        protected OrderRepository $orderRepository,
        private readonly AfbDispatchService $afbDispatchService,
        private readonly AfbDocumentGenerator $afbDocumentGenerator,
        private readonly AnamnesisGvlFormResolver $anamnesisGvlFormResolver,
        private readonly CrmMailService $crmMailService,
        private readonly FormService $formService,
    ) {}

    public function deleteAfbPersonDocument(int $orderId, int $personDocumentId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        $doc = AfbPersonDocument::where('order_id', $order->id)->findOrFail($personDocumentId);
        $doc->delete();

        return response()->json([
            'message' => 'AFB verwijderd. De order kan nu opnieuw worden verstuurd.',
        ]);
    }

    public function sendAfb(int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if ($order->isHerniapoli()) {
            return response()->json(['message' => 'AFB verzending is niet van toepassing voor Herniapoli orders.'], 422);
        }

        try {
            $queued = $this->afbDispatchService->queueLateBookingForOrder($order);

            return response()->json([
                'message' => $queued > 0
                    ? "AFB verstuurd naar {$queued} afdeling(en)."
                    : 'AFB was al verstuurd of condities zijn niet van toepassing.',
            ]);
        } catch (Throwable $e) {
            Log::error('AFB dispatch mislukt', ['order_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'AFB versturen mislukt: '.$e->getMessage()], 500);
        }
    }

    public function afbSendPage(int $orderId): View
    {
        $order = $this->orderRepository->with([
            'salesLead.persons',
            'orderItems.product',
            'orderItems.person',
            'orderItems.resourceOrderItems.resource.clinicDepartment.clinic',
            'afbPersonDocuments.dispatch.clinicDepartment',
        ])->findOrFail($orderId);

        $initialRows = $order->afbStatusRows()->map(fn ($row) => [
            'department_id'    => $row['department']->id,
            'department_name'  => $row['department']->name,
            'clinic_id'        => $row['department']->clinic_id,
            'clinic_name'      => $row['department']->clinic?->name,
            'person_id'        => $row['person']?->id,
            'person_name'      => $row['person']?->name,
            'dispatch_id'      => $row['dispatch']?->id,
            'dispatch_sent_at' => $row['dispatch']?->sent_at?->format('d-m-Y H:i'),
            'dispatch_pdf_url' => $row['dispatch'] ? route('admin.clinic-guide.afb-pdf.view', ['personDocumentId' => $row['dispatch']->id]) : null,
            'delete_url'       => $row['dispatch'] ? route('admin.orders.afb.delete', ['orderId' => $order->id, 'personDocumentId' => $row['dispatch']->id]) : null,
        ])->values();

        return view('admin::orders.afb-send', [
            'order'       => $order,
            'initialRows' => $initialRows,
        ]);
    }

    public function afbSendPrepare(int $orderId, int $departmentId): JsonResponse
    {
        $order = $this->orderRepository->with([
            'salesLead.persons',
            'orderItems.product.partnerProducts.clinics',
            'orderItems.person',
            'orderItems.resourceOrderItems.resource.clinicDepartment.clinic',
        ])->findOrFail($orderId);

        $department = ClinicDepartment::with('clinic')->findOrFail($departmentId);
        $personId = request()->query('person_id') ? (int) request()->query('person_id') : null;

        $recipientEmail = $department->email ?? '';
        $clinic = $department->clinic;

        $person = $personId
            ? $order->orderItems->pluck('person')->filter()->firstWhere('id', $personId)
            : null;

        $subject = sprintf(
            'AFB Manuell - %s (Order %s)',
            $clinic->registration_form_clinic_name ?: $clinic->name,
            $order->order_number ?: $order->id
        );

        $body = view('adminc.afb.dispatch_email', [
            'clinic'       => $clinic,
            'type'         => AfbDispatchType::INDIVIDUAL->value,
            'orderNumbers' => [$order->order_number ?: (string) $order->id],
            'sentAt'       => now()->format('d-m-Y H:i'),
        ])->render();

        $attachmentPreviews = [];

        $afbResult = $this->afbDocumentGenerator->renderHtmlForOrderAndDepartment($order, $department, $person);
        if ($afbResult['person']) {
            $attachmentPreviews[] = [
                'name' => sprintf('AFB - %s.pdf', $afbResult['person']->name ?? 'Patiënt'),
                'type' => 'afb',
            ];
        } else {
            $attachmentPreviews[] = [
                'name' => 'AFB - Patiënt.pdf',
                'type' => 'afb',
            ];
        }

        if ($person) {
            $anamnesisRecords = $this->anamnesisGvlFormResolver->loadForOrder($order);
            $anamnesis = $this->anamnesisGvlFormResolver->resolveForPerson($anamnesisRecords, $order->id, $person->id);
            $completedForms = $this->anamnesisGvlFormResolver->completedFormsForAnamnesis($anamnesis);
            $formCount = $completedForms->count();

            foreach ($completedForms as $index => $gvlForm) {
                $label = $gvlForm->gvl_form_type?->label() ?? 'GVL';
                $attachmentPreviews[] = [
                    'name' => $formCount > 1
                        ? sprintf('%s - %s (%d van %d).pdf', $label, $person->name ?? 'Patiënt', $index + 1, $formCount)
                        : sprintf('%s - %s.pdf', $label, $person->name ?? 'Patiënt'),
                    'type' => 'gvl',
                ];
            }
        }

        return response()->json([
            'subject'         => $subject,
            'body'            => $body,
            'recipient_email' => $recipientEmail,
            'clinic_name'     => $clinic->name,
            'department_name' => $department->name,
            'person_name'     => $person?->name,
            'attachments'     => $attachmentPreviews,
        ]);
    }

    public function afbSendManual(Request $request, int $orderId, int $departmentId): JsonResponse
    {
        $request->validate([
            'subject'   => 'required|string|max:500',
            'reply'     => 'required|string',
            'reply_to'  => 'required|email',
            'person_id' => 'nullable|integer',
        ]);

        $order = Order::with([
            'salesLead.persons',
            'orderItems.product.partnerProducts.clinics',
            'orderItems.person.address',
            'orderItems.resourceOrderItems.resource.clinicDepartment.clinic',
        ])->findOrFail($orderId);

        $department = ClinicDepartment::with('clinic')->findOrFail($departmentId);
        $personId = $request->input('person_id') ? (int) $request->input('person_id') : null;
        $person = $personId
            ? $order->orderItems->pluck('person')->filter()->firstWhere('id', $personId)
            : null;

        try {
            $dispatch = AfbDispatch::create([
                'clinic_id'            => $department->clinic_id,
                'clinic_department_id' => $departmentId,
                'type'                 => AfbDispatchType::INDIVIDUAL->value,
                'status'               => AfbDispatchStatus::FAILED->value,
                'attempt'              => 1,
                'last_attempt_at'      => now(),
            ]);

            $generatedDocs = $this->afbDocumentGenerator->generateForOrderAndDepartment($order, $department);

            if ($person) {
                $generatedDocs = array_values(array_filter($generatedDocs, fn ($doc) => $doc['person_id'] === $personId || $doc['person_id'] === null));
            }

            $email = $this->crmMailService->createEmail([
                'subject'   => $request->input('subject'),
                'reply'     => $request->input('reply'),
                'reply_to'  => [$request->input('reply_to')],
                'name'      => 'AFB handmatige verzending',
                'source'    => 'web',
                'user_type' => 'user',
                'clinic_id' => $department->clinic_id,
            ]);

            foreach ($generatedDocs as $doc) {
                $this->afbDispatchService->syncToMailDisk($doc['file_path']);

                $email->attachments()->create([
                    'name'         => $doc['file_name'],
                    'path'         => $doc['file_path'],
                    'size'         => Storage::size($doc['file_path']),
                    'content_type' => 'application/pdf',
                ]);
            }

            $this->afbDispatchService->attachGvlPdfsToEmail($email, $generatedDocs, $order);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $uploadedFile) {
                    $path = $uploadedFile->store('afb/manual-attachments', 'public');
                    $email->attachments()->create([
                        'name'         => $uploadedFile->getClientOriginalName(),
                        'path'         => $path,
                        'size'         => $uploadedFile->getSize(),
                        'content_type' => $uploadedFile->getMimeType(),
                    ]);
                }
            }

            $this->crmMailService->sendEmail($email);

            DB::transaction(function () use ($generatedDocs, $dispatch, $email, $order) {
                foreach ($generatedDocs as $doc) {
                    AfbPersonDocument::create([
                        'afb_dispatch_id' => $dispatch->id,
                        'order_id'        => $order->id,
                        'order_item_ids'  => $doc['order_item_ids'],
                        'person_id'       => $doc['person_id'],
                        'patient_name'    => $doc['patient_name'],
                        'file_name'       => $doc['file_name'],
                        'file_path'       => $doc['file_path'],
                        'sent_at'         => now(),
                    ]);
                }

                $dispatch->update([
                    'email_id' => $email->id,
                    'status'   => AfbDispatchStatus::SUCCESS->value,
                    'sent_at'  => now(),
                ]);
            });

            return response()->json([
                'message' => 'AFB succesvol verzonden naar '.$department->name.'.',
            ]);
        } catch (Throwable $e) {
            Log::error('Handmatige AFB dispatch mislukt', [
                'order_id'             => $orderId,
                'clinic_department_id' => $departmentId,
                'error'                => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'AFB versturen mislukt: '.$e->getMessage(),
            ], 500);
        }
    }

    public function afbSendAttachment(int $orderId, int $departmentId, string $type, ?int $personId = null): Response
    {
        $order = Order::with([
            'salesLead.persons',
            'orderItems.product.partnerProducts.clinics',
            'orderItems.person.address',
            'orderItems.resourceOrderItems.resource.clinicDepartment.clinic',
        ])->findOrFail($orderId);

        $department = ClinicDepartment::with('clinic')->findOrFail($departmentId);

        if ($type === 'afb') {
            $person = $personId
                ? $order->orderItems->pluck('person')->filter()->firstWhere('id', $personId)
                : null;

            $result = $this->afbDocumentGenerator->renderHtmlForOrderAndDepartment($order, $department, $person);

            $pdf = Pdf::loadHTML($result['html'])
                ->setPaper('A4', 'portrait');

            return $pdf->download(sprintf('afb_%s_%s.pdf',
                Str::slug($department->clinic->name),
                $order->order_number ?: $order->id
            ));
        }

        if ($type === 'gvl' && $personId) {
            $anamnesisRecords = $this->anamnesisGvlFormResolver->loadForOrder($order);
            $anamnesis = $this->anamnesisGvlFormResolver->resolveForPerson($anamnesisRecords, $order->id, $personId);
            $gvlFormRecord = $this->anamnesisGvlFormResolver->completedFormsForAnamnesis($anamnesis)->first();

            if (! $gvlFormRecord) {
                abort(404, 'Geen GVL formulier gevonden.');
            }

            $formId = $gvlFormRecord->gvl_form_id;

            if (! $formId) {
                abort(404, 'Geen geldig GVL formulier ID.');
            }

            $response = $this->formService->downloadForm($formId);

            if (! $response->successful()) {
                abort(502, 'GVL PDF ophalen mislukt.');
            }

            return response($response->body(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="gvl-%d.pdf"', $personId),
            ]);
        }

        abort(404);
    }
}
