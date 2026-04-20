<?php

namespace App\Services\Afb;

use App\Enums\AfbDispatchStatus;
use App\Enums\AfbDispatchType;
use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Jobs\SendAfbDispatchJob;
use App\Models\AfbDispatch;
use App\Models\AfbPersonDocument;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ResourceOrderItem;
use App\Services\Mail\CrmMailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;

class AfbDispatchService
{
    public function __construct(
        private readonly AfbDocumentGenerator $afbDocumentGenerator,
        private readonly CrmMailService $crmMailService
    ) {}

    public function queueDailyBatchDispatches(Carbon $date): int
    {
        $groupedOrderIds = ResourceOrderItem::onDate($date)
            ->forAfbDispatch()
            ->with(['resource:id,clinic_department_id', 'orderItem:id,order_id'])
            ->get()
            ->groupBy(fn ($roi) => (int) $roi->resource->clinic_department_id)
            ->map(fn ($rois) => $rois->pluck('orderItem.order_id')->map(fn ($id) => (int) $id)->unique()->values()->all())
            ->all();

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

        if (! $this->getAvbDispatchReadiness($order)['is_late']) {
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
                $generatedDocuments[] = [
                    'order' => $order,
                    ...$this->afbDocumentGenerator->generateForOrderAndDepartment($order, $department),
                ];
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

            $this->crmMailService->sendEmail($email, EmailFolderEnum::SENT);

            DB::transaction(function () use ($generatedDocuments, $dispatch, $email, $departmentId) {
                foreach ($generatedDocuments as $generatedDocument) {
                    AfbPersonDocument::create([
                        'afb_dispatch_id' => $dispatch->id,
                        'order_id'        => $generatedDocument['order']->id,
                        'order_item_ids'  => $generatedDocument['order']->orderItems
                            ->where('status', '!=', OrderItemStatus::LOST)
                            ->filter(fn ($item) => $item->resourceOrderItems
                                ->contains(fn ($roi) => $roi->resource?->clinic_department_id === $departmentId))
                            ->pluck('id')
                            ->values()
                            ->all(),
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
     * @return bool true, if first examination is within the next 24 hours, and thus should be sent as late booking AFB.
     */
    public function shouldSendAsLateBooking(Order $order): bool
    {
        if (! $order->first_examination_at) {
            return false;
        }

        $examAt = Carbon::parse($order->first_examination_at);

        if ($examAt->isPast()) {
            return false;
        }

        // Avoid Carbon 3 float diffInHours edge cases: compare against a fixed horizon.
        return $examAt->lessThanOrEqualTo(now()->copy()->addHours(24));
    }

    /**
     * Geeft de AFB dispatch-gereedheid terug voor weergave op de order view.
     *
     * @return array{is_ready: bool, is_late: bool, is_all_sent: bool, needs_manual_send: bool, is_herniapoli: bool, planned_at: \Carbon\Carbon|null, reasons: list<string>}
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
        $examAt = $order->first_examination_at ? Carbon::parse($order->first_examination_at) : null;

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
            ? $examAt->copy()->subDay()->setTime(6, 0, 0)
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
        $lastDocument = AfbPersonDocument::query()
            ->where('order_id', $orderId)
            ->whereHas('dispatch', fn ($q) => $q
                ->where('clinic_department_id', $departmentId)
                ->where('status', AfbDispatchStatus::SUCCESS->value))
            ->latest('sent_at')
            ->first();

        if (! $lastDocument || $lastDocument->order_item_ids === null) {
            return false;
        }

        // New active items linked to this department that were not in the last dispatch
        $hasNewItems = OrderItem::query()
            ->where('order_id', $orderId)
            ->where('status', '!=', OrderItemStatus::LOST->value)
            ->whereHas('resourceOrderItems', fn ($q) => $q
                ->whereHas('resource', fn ($q) => $q->where('clinic_department_id', $departmentId)))
            ->whereNotIn('id', $lastDocument->order_item_ids)
            ->exists();

        if ($hasNewItems) {
            return true;
        }

        // Items that were in the last dispatch but are now LOST (clinic must receive updated AFB)
        return OrderItem::query()
            ->whereIn('id', $lastDocument->order_item_ids)
            ->where('status', OrderItemStatus::LOST->value)
            ->exists();
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
