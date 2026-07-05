<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Enums\ActivityType;
use App\Enums\NotificationReferenceType;
use App\Events\OrderMarkedAsSent;
use App\Events\PatientNotifyEvent;
use App\Models\Order;
use App\Models\OrderPersonConfirmation;
use App\Repositories\OrderRepository;
use App\Services\Mail\CrmMailService;
use App\Services\OrderMailService;
use App\Services\Storage\DocumentStorage;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Models\Person;
use Webkul\Core\Traits\PDFHandler;

/**
 * Order confirmation endpoints: the confirm page, confirmation letters
 * (combined and per person), PDF previews/exports and mark-as-sent flows.
 */
class OrderConfirmationController extends Controller
{
    use PDFHandler;

    public function __construct(
        protected OrderRepository $orderRepository,
        private readonly OrderMailService $orderMailService,
        private readonly CrmMailService $crmMailService,
    ) {}

    public function confirm(int $orderId): View
    {
        $order = $this->orderRepository->findOrFail($orderId);

        $order->load([
            'orderItems.product.productGroup',
            'orderItems.person',
            'salesLead.lead',
            'salesLead.persons',
            'salesLead.contactPerson',
            'orderChecks',
            'personConfirmations',
        ]);

        $orderEmailOptions = $this->orderMailService->getEmailOptions($order);

        $personsStatus = [];
        $combineOrder = $order->combine_order !== false;

        if (! $combineOrder && $order->salesLead) {
            $personsStatus = $this->buildPersonsConfirmationStatus($order)->all();
        }

        return view('admin::orders.confirm', [
            'orders'            => $order,
            'orderEmailOptions' => $orderEmailOptions,
            'combineOrder'      => $combineOrder,
            'personsStatus'     => $personsStatus,
        ]);
    }

    public function mailPreview(int $orderId): JsonResponse
    {
        $order = $this->orderRepository->findOrFail($orderId);

        $order->load([
            'orderItems.product',
            'orderItems.person',
            'salesLead.lead',
            'salesLead.contactPerson',
        ]);

        if (! $order->salesLead) {
            return response()->json([
                'message' => 'Order heeft geen gekoppelde sales.',
            ], 422);
        }

        $mailData = $this->orderMailService->buildMailData($order);

        return response()->json($mailData);
    }

    public function markAsSent(Request $request, int $orderId): JsonResponse
    {
        $order = $this->orderRepository->with('salesLead.lead.department')->findOrFail($orderId);

        // Dispatch event - listeners will handle PDF activity creation
        OrderMarkedAsSent::dispatch($order, auth()->id());

        return response()->json([
            'message' => 'Orderstatus gezet op verstuurd.',
        ], 200);
    }

    /**
     * Get list of available order confirmation templates.
     */
    public function getConfirmationTemplates(): JsonResponse
    {
        $templatesPath = resource_path('views/adminc/email_templates/order');
        $templates = [];

        if (File::exists($templatesPath)) {
            $files = File::files($templatesPath);

            foreach ($files as $file) {
                $filename = $file->getFilename();
                if (str_ends_with($filename, '.blade.php')) {
                    $name = str_replace('.blade.php', '', $filename);
                    $templates[] = [
                        'name'  => $name,
                        'label' => ucfirst(str_replace('_', ' ', $name)),
                    ];
                }
            }
        }

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Get rendered order confirmation template content.
     */
    public function getConfirmationTemplateContent(Request $request, int $orderId): JsonResponse
    {
        $templateIdentifier = $request->query('template');

        if (! $templateIdentifier) {
            return response()->json([
                'error' => 'Template identifier is required',
            ], 400);
        }

        try {
            $rendered = $this->crmMailService->renderHtmlForEntities(
                $templateIdentifier,
                ['order' => $orderId]
            );

            return response()->json([
                'data' => [
                    'content' => $rendered['html'],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Order confirmation template rendering error: '.$e->getMessage(), [
                'template'  => $templateIdentifier,
                'order_id'  => $orderId,
                'exception' => $e,
            ]);

            return response()->json([
                'error'   => 'Template not found or error rendering template',
                'message' => $e->getMessage(),
                'trace'   => config('app.debug') ? $e->getTraceAsString() : null,
            ], str_contains($e->getMessage(), 'not found') ? 404 : 500);
        }
    }

    /**
     * Save confirmation letter content.
     */
    public function saveConfirmationLetter(Request $request, int $orderId): JsonResponse
    {
        $request->validate([
            'content' => 'nullable|string',
        ]);

        $order = $this->orderRepository->findOrFail($orderId);

        $order->update([
            'confirmation_letter_content' => $request->input('content'),
        ]);

        return response()->json([
            'message' => 'Orderbevestiging opgeslagen.',
            'data'    => [
                'content' => $order->confirmation_letter_content,
            ],
        ]);
    }

    /**
     * Export confirmation letter to PDF.
     */
    public function exportConfirmationLetterPDF(Request $request, int $orderId)
    {
        $order = $this->orderRepository->findOrFail($orderId);

        if (empty($order->confirmation_letter_content)) {
            return response()->json([
                'error' => 'Geen orderbevestiging beschikbaar om te exporteren.',
            ], 422);
        }

        $fileName = 'order-bevestiging-'.$order->id.'-'.date('Y-m-d');

        return $this->downloadPDF($order->confirmation_letter_content, $fileName);
    }

    /**
     * Inline PDF preview for the confirmation letter (same render as export, without persisting).
     */
    public function previewConfirmationLetterPdf(Request $request, int $orderId): Response
    {
        $this->orderRepository->findOrFail($orderId);

        $request->validate([
            'content' => 'required|string',
        ]);

        $binary = $this->pdfBinaryFromHtml($request->input('content'));

        return response($binary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="order-bevestiging-preview.pdf"',
        ]);
    }

    // ---- Per-person confirmation endpoints (combine_order = false) ----

    public function personsConfirmationStatus(int $orderId): JsonResponse
    {
        $order = Order::with(['salesLead.persons', 'personConfirmations'])->findOrFail($orderId);

        if (! $order->salesLead) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data'          => $this->buildPersonsConfirmationStatus($order)->values(),
            'all_confirmed' => $order->allPersonsConfirmed(),
        ]);
    }

    public function getPersonConfirmationTemplateContent(Request $request, int $orderId, int $personId): JsonResponse
    {
        $templateIdentifier = $request->query('template');

        if (! $templateIdentifier) {
            return response()->json(['error' => 'Template identifier is required'], 400);
        }

        try {
            $rendered = $this->crmMailService->renderHtmlForEntities(
                $templateIdentifier,
                ['order' => $orderId, 'person' => $personId]
            );

            return response()->json(['data' => ['content' => $rendered['html']]]);
        } catch (Exception $e) {
            Log::error('Per-person confirmation template error', [
                'order_id'  => $orderId,
                'person_id' => $personId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json(
                ['error' => $e->getMessage()],
                str_contains($e->getMessage(), 'not found') ? 404 : 500
            );
        }
    }

    public function getPersonConfirmationContent(int $orderId, int $personId): JsonResponse
    {
        Order::findOrFail($orderId);
        Person::findOrFail($personId);

        $confirmation = OrderPersonConfirmation::where('order_id', $orderId)
            ->where('person_id', $personId)
            ->first();

        return response()->json([
            'data' => ['content' => $confirmation?->confirmation_letter_content ?? ''],
        ]);
    }

    public function savePersonConfirmationLetter(Request $request, int $orderId, int $personId): JsonResponse
    {
        $request->validate(['content' => 'nullable|string']);

        Order::findOrFail($orderId);
        Person::findOrFail($personId);

        $confirmation = OrderPersonConfirmation::updateOrCreate(
            ['order_id' => $orderId, 'person_id' => $personId],
            ['confirmation_letter_content' => $request->input('content')]
        );

        return response()->json([
            'message' => 'Orderbevestiging voor persoon opgeslagen.',
            'data'    => ['content' => $confirmation->confirmation_letter_content],
        ]);
    }

    public function previewPersonConfirmationPdf(Request $request, int $orderId, int $personId): Response
    {
        Order::findOrFail($orderId);

        $request->validate(['content' => 'required|string']);

        $binary = $this->pdfBinaryFromHtml($request->input('content'));

        return response($binary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="order-bevestiging-preview.pdf"',
        ]);
    }

    public function personMailPreview(int $orderId, int $personId): JsonResponse
    {
        $order = Order::with([
            'orderItems.product',
            'orderItems.person',
            'salesLead.lead',
            'salesLead.contactPerson',
        ])->findOrFail($orderId);

        $person = Person::findOrFail($personId);

        $mailData = $this->orderMailService->buildMailData($order, $person);

        $emailOptions = [];
        foreach ($person->emails ?? [] as $email) {
            if (! empty($email['value'])) {
                $emailOptions[] = [
                    'value'      => $email['value'],
                    'is_default' => ! empty($email['is_default']),
                ];
            }
        }

        return response()->json(array_merge($mailData, [
            'default_email' => $this->defaultEmailFor($person),
            'emails'        => $emailOptions,
            'person_name'   => $person->name,
        ]));
    }

    public function markPersonAsSent(Request $request, int $orderId, int $personId): JsonResponse
    {
        $order = Order::with('salesLead.lead.department')->findOrFail($orderId);
        $person = Person::findOrFail($personId);

        $confirmation = OrderPersonConfirmation::where('order_id', $orderId)
            ->where('person_id', $personId)
            ->first();

        if ($confirmation?->isLetterSaved()) {
            try {
                $this->storePersonConfirmationPdf($order, $person, $confirmation, auth()->id());
            } catch (Throwable $e) {
                Log::error('Failed to create per-person confirmation PDF', [
                    'order_id'  => $orderId,
                    'person_id' => $personId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        OrderPersonConfirmation::updateOrCreate(
            ['order_id' => $orderId, 'person_id' => $personId],
            ['email_sent_at' => now()]
        );

        $allDone = $order->allPersonsConfirmed();

        if ($allDone) {
            $order->update([
                'pipeline_stage_id' => Order::orderSendByDepartmentStageId($order->salesLead?->lead?->department),
            ]);

            OrderMarkedAsSent::dispatch($order, auth()->id());
        }

        return response()->json([
            'message'       => 'Persoon bevestigd.',
            'all_confirmed' => $allDone,
        ]);
    }

    /**
     * Confirmation status per sales-lead person (letter saved, files present, email sent).
     * Requires 'salesLead.persons' and 'personConfirmations' to be eager-loaded.
     */
    private function buildPersonsConfirmationStatus(Order $order): Collection
    {
        $confirmations = $order->personConfirmations->keyBy('person_id');

        return $order->salesLead->persons->map(function (Person $person) use ($order, $confirmations) {
            $confirmation = $confirmations->get($person->id);
            $hasFiles = Activity::query()
                ->where('order_id', $order->id)
                ->where('person_id', $person->id)
                ->where('type', ActivityType::FILE)
                ->exists();

            return [
                'id'            => $person->id,
                'name'          => $person->name,
                'email'         => $this->defaultEmailFor($person),
                'letter_saved'  => $confirmation?->isLetterSaved() ?? false,
                'has_files'     => $hasFiles,
                'email_sent'    => $confirmation?->isEmailSent() ?? false,
                'email_sent_at' => $confirmation?->email_sent_at?->toIso8601String(),
            ];
        });
    }

    private function defaultEmailFor(Person $person): ?string
    {
        return collect($person->emails ?? [])
            ->firstWhere('is_default', true)['value']
            ?? collect($person->emails ?? [])->first()['value']
            ?? null;
    }

    private function storePersonConfirmationPdf(Order $order, Person $person, OrderPersonConfirmation $confirmation, ?int $userId): void
    {
        // Use the same HTML → PDF pipeline as the preview endpoints (PDFHandler::pdfBinaryFromHtml)
        // so that font, sizing and layout are identical between preview and stored attachments.
        $pdfContent = $this->pdfBinaryFromHtml($confirmation->confirmation_letter_content);

        $activityRepository = app(ActivityRepository::class);
        $documentStorage = app(DocumentStorage::class);

        $activity = $activityRepository->create([
            'type'              => ActivityType::FILE,
            'title'             => 'Orderbevestiging PDF – '.$person->name,
            'comment'           => 'Automatisch gegenereerde orderbevestiging voor '.$person->name,
            'is_done'           => true,
            'user_id'           => $userId,
            'order_id'          => $order->id,
            'person_id'         => $person->id,
            'additional'        => ['document_type' => 'order_confirmation'],
        ]);

        $fileName = 'order-bevestiging-'.$order->id.'-'.$person->id.'-'.date('Y-m-d').'.pdf';
        $filePath = 'activities/'.$activity->id.'/'.$fileName;
        $documentStorage->put($filePath, $pdfContent);

        $activity->files()->create([
            'name' => $fileName,
            'path' => $filePath,
        ]);

        $activity->portalPersons()->attach($person->id);

        PatientNotifyEvent::dispatch(
            $person->id,
            'Orderbevestiging '.$order->order_number,
            NotificationReferenceType::FILE,
            $activity->id,
            false,
            $userId
        );
    }
}
