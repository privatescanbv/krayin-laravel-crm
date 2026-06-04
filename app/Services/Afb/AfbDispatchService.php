<?php

namespace App\Services\Afb;

use App\Enums\AfbDispatchStatus;
use App\Enums\AfbDispatchType;
use App\Enums\FormStatus;
use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Jobs\SendAfbDispatchJob;
use App\Models\AfbDispatch;
use App\Models\AfbPersonDocument;
use App\Models\Anamnesis;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ResourceOrderItem;
use App\Services\FormService;
use App\Services\Mail\CrmMailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Webkul\Email\Models\Email;

class AfbDispatchService
{
    public const AFB_LATE_BOOKING_CUTOFF_HOUR = 11;

    public function __construct(
        private readonly AfbDocumentGenerator $afbDocumentGenerator,
        private readonly CrmMailService $crmMailService,
        private readonly FormService $formService,
    ) {}

    /**
     * @return array<int, array<int>>
     */
    public function resolveDailyBatchGroups(Carbon $date): array
    {
        return ResourceOrderItem::onDate($date)
            ->forAfbDispatch()
            ->with(['resource:id,clinic_department_id', 'orderItem:id,order_id'])
            ->get()
            ->groupBy(fn ($roi) => (int) $roi->resource->clinic_department_id)
            ->map(fn ($rois) => $rois->pluck('orderItem.order_id')->map(fn ($id) => (int) $id)->unique()->values()->all())
            ->all();
    }

    /**
     * Preview what the daily batch would queue and send, without jobs, e-mail or database writes.
     *
     * @return array{
     *     date: string,
     *     job_count: int,
     *     batches: list<array{
     *         department_id: int,
     *         department_name: string,
     *         clinic_name: string,
     *         department_email: ?string,
     *         resource_slot_count: int,
     *         queued_order_ids: list<int>,
     *         would_send: list<array{order_id: int, order_number: ?string, stage: ?string}>,
     *         skipped: list<array{order_id: int, order_number: ?string, stage: ?string, reason: string}>,
     *         would_dispatch_email: bool
     *     }>
     * }
     */
    public function previewDailyBatchDispatches(Carbon $date): array
    {
        $groupedOrderIds = $this->resolveDailyBatchGroups($date);

        $slotsByDepartment = ResourceOrderItem::onDate($date)
            ->forAfbDispatch()
            ->with(['resource:id,clinic_department_id'])
            ->get()
            ->groupBy(fn ($roi) => (int) $roi->resource->clinic_department_id)
            ->map(fn ($rois) => $rois->count());

        $batches = [];

        foreach ($groupedOrderIds as $departmentId => $orderIds) {
            $department = ClinicDepartment::with('clinic')->find($departmentId);

            $orders = Order::query()
                ->whereIn('id', $orderIds)
                ->with('stage')
                ->get()
                ->keyBy('id');

            $wouldSend = [];
            $skipped = [];

            foreach ($orderIds as $orderId) {
                $order = $orders->get($orderId);
                $skipReason = $this->getDailyBatchOrderSkipReason($order, (int) $departmentId);

                $row = [
                    'order_id'     => $orderId,
                    'order_number' => $order?->order_number,
                    'stage'        => $order?->stage?->name,
                ];

                if ($skipReason === null) {
                    $wouldSend[] = $row;
                } else {
                    $skipped[] = [...$row, 'reason' => $skipReason];
                }
            }

            $batches[] = [
                'department_id'        => (int) $departmentId,
                'department_name'      => $department?->name ?? "(afdeling #{$departmentId})",
                'clinic_name'          => $department?->clinic?->name ?? '—',
                'department_email'     => $department?->email,
                'resource_slot_count'  => (int) ($slotsByDepartment[(int) $departmentId] ?? 0),
                'queued_order_ids'     => $orderIds,
                'would_send'           => $wouldSend,
                'skipped'              => $skipped,
                'would_dispatch_email' => $wouldSend !== [],
            ];
        }

        return [
            'date'      => $date->toDateString(),
            'job_count' => count($batches),
            'batches'   => $batches,
        ];
    }

    public function getDailyBatchOrderSkipReason(?Order $order, int $departmentId): ?string
    {
        if ($order === null) {
            return 'Order niet gevonden';
        }

        if ($order->isHerniapoli()) {
            return 'Herniapoli (geen AFB)';
        }

        if (! $this->isInDispatchableStage($order)) {
            return 'Order staat niet in de juiste status voor AFB dispatch';
        }

        if ($this->isAlreadySentToDepartment((int) $order->id, $departmentId)
            && ! $this->hasUnincludedActiveItems((int) $order->id, $departmentId)) {
            return 'Al succesvol verzonden naar deze afdeling';
        }

        return null;
    }

    public function queueDailyBatchDispatches(Carbon $date): int
    {
        $groupedOrderIds = $this->resolveDailyBatchGroups($date);

        foreach ($groupedOrderIds as $departmentId => $orderIds) {
            SendAfbDispatchJob::dispatch($departmentId, $orderIds, AfbDispatchType::BATCH->value);
        }

        return count($groupedOrderIds);
    }

    public function queueLateBookingForOrder(Order $order): int
    {
        if ($order->isHerniapoli()) {
            return 0;
        }

        if (! $this->isInDispatchableStage($order)) {
            return 0;
        }

        $queued = 0;
        $departmentIds = $this->getUniqueDepartmentIdsForOrder((int) $order->id);

        foreach ($departmentIds as $departmentId) {
            if ($this->isAlreadySentToDepartment((int) $order->id, (int) $departmentId)
                && ! $this->hasUnincludedActiveItems((int) $order->id, (int) $departmentId)) {
                continue;
            }

            SendAfbDispatchJob::dispatch((int) $departmentId, [(int) $order->id], AfbDispatchType::INDIVIDUAL->value);
            $queued++;
        }

        return $queued;
    }

    public function sendDispatch(
        int $departmentId,
        array $orderIds,
        AfbDispatchType $type,
        int $attempt = 1
    ): AfbDispatch {
        $department = ClinicDepartment::with('clinic')->findOrFail($departmentId);

        $orders = Order::query()
            ->whereIn('id', $orderIds)
            ->with([
                'salesLead.persons',
                'orderItems.resourceOrderItems.resource.clinicDepartment',
                'orderItems.person',
                'stage',
            ])
            ->get()
            ->keyBy('id');

        $unsentOrders = collect($orderIds)
            ->map(fn (int $id) => $orders->get($id))
            ->filter(fn (?Order $order) => $order !== null)
            ->filter(fn (Order $order) => ! $order->isHerniapoli())
            ->filter(fn (Order $order) => $this->isInDispatchableStage($order))
            ->filter(fn (Order $order) => ! $this->isAlreadySentToDepartment((int) $order->id, $departmentId)
                || $this->hasUnincludedActiveItems((int) $order->id, $departmentId))
            ->values();

        $dispatch = AfbDispatch::create([
            'clinic_id'            => $department->clinic_id,
            'clinic_department_id' => $departmentId,
            'type'                 => $type->value,
            'status'               => AfbDispatchStatus::FAILED->value,
            'attempt'              => max(1, $attempt),
            'last_attempt_at'      => now(),
        ]);

        if ($unsentOrders->isEmpty()) {
            $dispatch->update([
                'status'  => AfbDispatchStatus::SUCCESS->value,
                'sent_at' => now(),
            ]);

            return $dispatch;
        }

        try {
            $generatedDocuments = [];

            foreach ($unsentOrders as $order) {
                $docs = $this->afbDocumentGenerator->generateForOrderAndDepartment($order, $department);
                foreach ($docs as $doc) {
                    $generatedDocuments[] = ['order' => $order, ...$doc];
                }
            }

            $email = $this->createDispatchEmail(
                $department,
                $type,
                $unsentOrders->pluck('order_number')->filter()->values()->all(),
                count($generatedDocuments)
            );

            foreach ($generatedDocuments as $generatedDocument) {
                $this->syncToMailDisk($generatedDocument['file_path']);

                $email->attachments()->create([
                    'name'         => $generatedDocument['file_name'],
                    'path'         => $generatedDocument['file_path'],
                    'size'         => Storage::size($generatedDocument['file_path']),
                    'content_type' => 'application/pdf',
                ]);
            }

            $this->attachGvlPdfsToEmail($email, $generatedDocuments);

            $this->crmMailService->sendEmail($email);

            DB::transaction(function () use ($generatedDocuments, $dispatch, $email) {
                foreach ($generatedDocuments as $generatedDocument) {
                    AfbPersonDocument::create([
                        'afb_dispatch_id' => $dispatch->id,
                        'order_id'        => $generatedDocument['order']->id,
                        'order_item_ids'  => $generatedDocument['order_item_ids'],
                        'person_id'       => $generatedDocument['person_id'],
                        'patient_name'    => $generatedDocument['patient_name'],
                        'file_name'       => $generatedDocument['file_name'],
                        'file_path'       => $generatedDocument['file_path'],
                        'sent_at'         => now(),
                    ]);
                }

                $dispatch->update([
                    'email_id' => $email->id,
                    'status'   => AfbDispatchStatus::SUCCESS->value,
                    'sent_at'  => now(),
                ]);
            });

            Log::info('AFB dispatch success', [
                'clinic_department_id' => $departmentId,
                'order_ids'            => $unsentOrders->pluck('id')->values()->all(),
                'timestamp'            => now()->toIso8601String(),
                'type'                 => $type->value,
                'status'               => AfbDispatchStatus::SUCCESS->value,
            ]);
        } catch (Throwable $e) {
            $dispatch->update([
                'status'        => AfbDispatchStatus::FAILED->value,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('AFB dispatch failed', [
                'clinic_department_id' => $departmentId,
                'order_ids'            => $unsentOrders->pluck('id')->values()->all(),
                'timestamp'            => now()->toIso8601String(),
                'type'                 => $type->value,
                'status'               => AfbDispatchStatus::FAILED->value,
                'error_message'        => $e->getMessage(),
            ]);

            throw $e;
        }

        return $dispatch->refresh();
    }

    /**
     * Whether a successful AFB was already sent for this order to this department.
     */
    public function isAlreadySentToDepartment(int $orderId, int $departmentId): bool
    {
        return AfbPersonDocument::query()
            ->where('order_id', $orderId)
            ->whereHas('dispatch', fn ($q) => $q
                ->where('clinic_department_id', $departmentId)
                ->where('status', AfbDispatchStatus::SUCCESS->value))
            ->exists();
    }

    /**
     * @return bool true when the batch deadline (the day before the examination at the cutoff hour)
     *              has passed, so the order missed the automatic batch and must be sent as a late booking.
     */
    public function shouldSendAsLateBooking(Order $order): bool
    {
        $examAt = $order->firstExaminationCarbon();

        if (! $examAt) {
            return false;
        }

        if ($examAt->isPast()) {
            return false;
        }

        return now()->greaterThan($this->getBatchDeadlineForExam($examAt));
    }

    /**
     * Whether the manual "AFB versturen" button in the order view should be enabled.
     *
     * Active when:
     *   - the current time is within [today AFB_LATE_BOOKING_CUTOFF_HOUR, tomorrow AFB_LATE_BOOKING_CUTOFF_HOUR]
     *   - the order's first examination falls in the same window
     *   - at least one department still has a pending AFB
     */
    public function isLateBookingWindowActive(Order $order): bool
    {
        if ($order->isHerniapoli()) {
            return false;
        }

        if (! $this->isInDispatchableStage($order)) {
            return false;
        }

        $now = now();
        $windowStart = $now->copy()->startOfDay()->setTime(self::AFB_LATE_BOOKING_CUTOFF_HOUR, 0);
        $windowEnd = $windowStart->copy()->addDay();

        if (! $now->between($windowStart, $windowEnd)) {
            return false;
        }

        $examAt = $order->firstExaminationCarbon();

        if (! $examAt || ! $examAt->between($windowStart, $windowEnd)) {
            return false;
        }

        $departmentIds = $this->getUniqueDepartmentIdsForOrder((int) $order->id);

        if (empty($departmentIds)) {
            return false;
        }

        return $this->hasPendingAfbForDepartments((int) $order->id, $departmentIds);
    }

    /**
     * Geeft de AFB dispatch-gereedheid terug voor weergave op de order view.
     *
     * @return array{is_ready: bool, is_late: bool, is_all_sent: bool, needs_manual_send: bool, is_herniapoli: bool, planned_at: Carbon|null, reasons: list<string>}
     */
    public function getAvbDispatchReadiness(Order $order): array
    {
        if ($order->isHerniapoli()) {
            return [
                'is_ready'          => false,
                'is_late'           => false,
                'is_all_sent'       => false,
                'needs_manual_send' => false,
                'is_herniapoli'     => true,
                'planned_at'        => null,
                'reasons'           => ['Herniapoli orders ontvangen geen AFB'],
            ];
        }

        $reasons = [];
        $examAt = $order->firstExaminationCarbon();

        if (! $this->isInDispatchableStage($order)) {
            $reasons[] = 'Order staat niet in de juiste status voor AFB dispatch';
        }

        if (! $examAt) {
            $reasons[] = 'Geen eerste onderzoekdatum ingesteld';
        } elseif ($examAt->isPast()) {
            $reasons[] = 'Eerste onderzoekdatum is verstreken';
        }

        $departmentIds = $this->getUniqueDepartmentIdsForOrder((int) $order->id);
        if ($departmentIds === []) {
            $reasons[] = 'Geen kliniekafdelingen gekoppeld aan order items';
        }

        $isReady = empty($reasons);
        $isLate = $isReady && $this->shouldSendAsLateBooking($order);
        $needsManualSend = $isLate && $this->hasPendingAfbForDepartments((int) $order->id, $departmentIds);

        $isAllSent = $isReady
            && ! $this->hasPendingAfbForDepartments((int) $order->id, $departmentIds)
            && AfbPersonDocument::query()
                ->where('order_id', $order->id)
                ->whereHas('dispatch', fn ($q) => $q->where('status', AfbDispatchStatus::SUCCESS->value))
                ->exists();

        $plannedAt = ($examAt && ! $examAt->isPast())
            ? $this->getBatchDeadlineForExam($examAt)
            : null;

        return [
            'is_ready'          => $isReady,
            'is_late'           => $isLate,
            'is_all_sent'       => $isAllSent,
            'needs_manual_send' => $needsManualSend,
            'is_herniapoli'     => false,
            'planned_at'        => $plannedAt,
            'reasons'           => $reasons,
        ];
    }

    /**
     * Of er al een succesvolle batch-verzending voor deze order is geweest.
     */
    public function hasSuccessfulBatchDispatchForOrder(Order $order): bool
    {
        return AfbPersonDocument::query()
            ->where('order_id', $order->id)
            ->whereHas('dispatch', fn ($q) => $q
                ->where('type', AfbDispatchType::BATCH->value)
                ->where('status', AfbDispatchStatus::SUCCESS->value))
            ->exists();
    }

    /**
     * Unique clinic department IDs from plannable order items (see {@see OrderItem::isPlannable()})
     * that have a resource booking with a department.
     *
     * @return array<int, int>
     */
    public function getUniqueDepartmentIdsForOrder(int $orderId): array
    {
        return OrderItem::query()
            ->where('order_id', $orderId)
            ->with([
                'product.partnerProducts.resourceType',
                'resourceOrderItems.resource',
            ])
            ->get()
            ->filter(fn (OrderItem $item) => $item->isPlannable())
            ->flatMap(fn (OrderItem $item) => $item->resourceOrderItems
                ->map(fn ($roi) => $roi->resource?->clinic_department_id))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Returns true when the last successful AFB for this order+department is out of date:
     * either a new active item was added that is not in the last dispatch, or an item that
     * was in the last dispatch has since been marked LOST (cancelled item → updated AFB needed).
     */
    public function hasUnincludedActiveItems(int $orderId, int $departmentId): bool
    {
        $documents = AfbPersonDocument::query()
            ->where('order_id', $orderId)
            ->whereHas('dispatch', fn ($q) => $q
                ->where('clinic_department_id', $departmentId)
                ->where('status', AfbDispatchStatus::SUCCESS->value))
            ->get();

        if ($documents->isEmpty()) {
            return false;
        }

        // Union of all item IDs covered across every per-person document for this order+department
        $allSentItemIds = $documents
            ->flatMap(fn ($doc) => $doc->order_item_ids ?? [])
            ->unique()
            ->values()
            ->all();

        if (empty($allSentItemIds)) {
            return false;
        }

        // New active items linked to this department not covered by any previous dispatch document
        $hasNewItems = OrderItem::query()
            ->where('order_id', $orderId)
            ->where('status', '!=', OrderItemStatus::LOST->value)
            ->whereHas('resourceOrderItems', fn ($q) => $q
                ->whereHas('resource', fn ($q) => $q->where('clinic_department_id', $departmentId)))
            ->whereNotIn('id', $allSentItemIds)
            ->exists();

        if ($hasNewItems) {
            return true;
        }

        // Items that were in any previous dispatch document but are now LOST
        return OrderItem::query()
            ->whereIn('id', $allSentItemIds)
            ->where('status', OrderItemStatus::LOST->value)
            ->exists();
    }

    public function attachGvlPdfsToEmail(Email $email, array $generatedDocuments): void
    {
        $personNames = collect($generatedDocuments)
            ->whereNotNull('person_id')
            ->mapWithKeys(fn ($doc) => [(int) $doc['person_id'] => $doc['patient_name'] ?? '']);

        foreach ($personNames->keys() as $personId) {
            $anamnesis = Anamnesis::query()
                ->where('person_id', $personId)
                ->whereNotNull('gvl_form_id')
                ->latest()
                ->first();

            if (! $anamnesis) {
                continue;
            }

            $formId = $anamnesis->gvl_form_id;

            try {
                if ($anamnesis->gvl_form_status !== FormStatus::Completed) {
                    continue;
                }

                $response = $this->formService->downloadForm($formId);

                if (! $response->successful()) {
                    Log::error('GVL PDF download mislukt', [
                        'person_id' => $personId,
                        'form_id'   => $formId,
                        'status'    => $response->status(),
                    ]);

                    continue;
                }

                $pdfContent = $response->body();
                $fileName = sprintf(
                    'gvl-%d-%s-%s.pdf',
                    $personId,
                    Str::slug($personNames->get($personId) ?? ''),
                    now()->format('Ymd')
                );
                $filePath = sprintf('afb/gvl/%d/%s', $personId, $fileName);

                Storage::put($filePath, $pdfContent);

                $email->attachments()->create([
                    'name'         => $fileName,
                    'path'         => $filePath,
                    'size'         => strlen($pdfContent),
                    'content_type' => 'application/pdf',
                ]);
            } catch (Throwable $e) {
                Log::error('GVL PDF bijlage mislukt', [
                    'person_id'     => $personId,
                    'form_id'       => $formId,
                    'error_message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * The moment the daily batch (afb:send-daily) is scheduled to pick up this examination:
     * the day before the examination at {@see self::AFB_LATE_BOOKING_CUTOFF_HOUR}:00.
     */
    private function getBatchDeadlineForExam(Carbon $examAt): Carbon
    {
        return $examAt->copy()->subDay()->setTime(self::AFB_LATE_BOOKING_CUTOFF_HOUR, 0, 0);
    }

    private function createDispatchEmail(
        ClinicDepartment $department,
        AfbDispatchType $type,
        array $orderNumbers,
        int $attachmentCount
    ): Email {
        $recipientEmail = $department->email
            ?? throw new RuntimeException("Geen e-mailadres voor afdeling ID {$department->id}");

        $clinic = $department->clinic;

        $subject = sprintf(
            'AFB %s - %s (%d Anlage%s)',
            $type === AfbDispatchType::BATCH ? 'Batch' : 'Individuell',
            $clinic->registration_form_clinic_name ?: $clinic->name,
            $attachmentCount,
            $attachmentCount === 1 ? '' : 'n'
        );

        $body = view('adminc.afb.dispatch_email', [
            'clinic'       => $clinic,
            'type'         => $type->value,
            'orderNumbers' => $orderNumbers,
            'sentAt'       => now()->format('d-m-Y H:i'),
        ])->render();

        return $this->crmMailService->createEmail([
            'subject'   => $subject,
            'reply'     => $body,
            'reply_to'  => [$recipientEmail],
            'name'      => 'AFB verzending',
            'source'    => 'system',
            'user_type' => 'user',
            'clinic_id' => $clinic->id,
        ]);
    }

    private function isInDispatchableStage(Order $order): bool
    {
        return in_array((int) $order->pipeline_stage_id, PipelineStage::getAfbDispatchAllowedStageIds(), true);
    }

    /**
     * Returns true when at least one department still needs an AFB for this order.
     * Includes previously dispatched departments where items have since changed.
     *
     * @param  array<int, int>  $activeDepartmentIds
     */
    private function hasPendingAfbForDepartments(int $orderId, array $activeDepartmentIds): bool
    {
        // Also include departments that previously received a successful dispatch — items may
        // have been marked LOST since then.
        $previouslyDispatchedIds = DB::table('afb_person_documents')
            ->join('afb_dispatches', 'afb_dispatches.id', '=', 'afb_person_documents.afb_dispatch_id')
            ->where('afb_person_documents.order_id', $orderId)
            ->where('afb_dispatches.status', AfbDispatchStatus::SUCCESS->value)
            ->pluck('afb_dispatches.clinic_department_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $allDepartmentIds = array_values(array_unique(array_merge($activeDepartmentIds, $previouslyDispatchedIds)));

        if ($allDepartmentIds === []) {
            return false;
        }

        foreach ($allDepartmentIds as $departmentId) {
            if (! $this->isAlreadySentToDepartment($orderId, (int) $departmentId)) {
                return true;
            }

            if ($this->hasUnincludedActiveItems($orderId, (int) $departmentId)) {
                return true;
            }
        }

        return false;
    }

    private function syncToMailDisk(string $path): void
    {
        if (Storage::exists($path)) {
            return;
        }

        $localDisk = Storage::disk('local');

        if (! $localDisk->exists($path)) {
            throw new RuntimeException("AFB bestand niet gevonden op enige disk: {$path}");
        }

        Storage::put($path, $localDisk->get($path));
    }
}
