<?php

namespace App\Services\Afb;

use App\Enums\AfbDispatchStatus;
use App\Enums\AfbDispatchType;
use App\Enums\OrderItemStatus;
use App\Jobs\SendAfbDispatchJob;
use App\Models\AfbDispatch;
use App\Models\AfbPersonDocument;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\OrderItem;
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
        $pairs = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('resource_orderitem', 'resource_orderitem.orderitem_id', '=', 'order_items.id')
            ->join('resources', 'resources.id', '=', 'resource_orderitem.resource_id')
            ->whereDate('resource_orderitem.from', $date->toDateString())
            ->whereNotNull('resources.clinic_department_id')
            ->select('orders.id as order_id', 'resources.clinic_department_id')
            ->distinct()
            ->get();

        $groupedOrderIds = [];

        foreach ($pairs as $pair) {
            $departmentId = (int) $pair->clinic_department_id;
            $groupedOrderIds[$departmentId] ??= [];
            $groupedOrderIds[$departmentId][] = (int) $pair->order_id;
        }

        foreach ($groupedOrderIds as $departmentId => $orderIds) {
            $orderIds = array_values(array_unique($orderIds));
            SendAfbDispatchJob::dispatch($departmentId, $orderIds, AfbDispatchType::BATCH->value);
        }

        return count($groupedOrderIds);
    }

    public function queueLateBookingForOrder(Order $order): int
    {
        if (! $this->shouldSendAsLateBooking($order)) {
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
            ])
            ->get()
            ->keyBy('id');

        $unsentOrders = collect($orderIds)
            ->map(fn (int $id) => $orders->get($id))
            ->filter(fn (?Order $order) => $order !== null)
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

        return now()->diffInSeconds($examAt, false) <= 86400;
    }

    /**
     * Handmatige AFB nodig: late-booking venster, planning met afdeling, en nog niet overal succesvol verstuurd.
     */
    public function needsManualLateAfb(Order $order): bool
    {
        if (! $this->shouldSendAsLateBooking($order)) {
            return false;
        }

        $departmentIds = $this->getUniqueDepartmentIdsForOrder((int) $order->id);

        if ($departmentIds === []) {
            return false;
        }

        foreach ($departmentIds as $departmentId) {
            if (! $this->isAlreadySentToDepartment((int) $order->id, (int) $departmentId)) {
                return true;
            }

            // Already sent, but a new order item was added after the last dispatch →
            // replacement AFB needed with updated content.
            if ($this->hasUnincludedActiveItems((int) $order->id, (int) $departmentId)) {
                return true;
            }
        }

        return false;
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
     * @return array<int, int>
     */
    public function getUniqueDepartmentIdsForOrder(int $orderId): array
    {
        return DB::table('order_items')
            ->join('resource_orderitem', 'resource_orderitem.orderitem_id', '=', 'order_items.id')
            ->join('resources', 'resources.id', '=', 'resource_orderitem.resource_id')
            ->where('order_items.order_id', $orderId)
            ->whereNotNull('resources.clinic_department_id')
            ->pluck('resources.clinic_department_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Returns true when there are active (non-lost) order items for this order that were not
     * included in the last successful AFB sent to the given department.
     * Only items linked to the given department are considered.
     * Lost items are intentionally excluded — they do not require a replacement AFB.
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

        return OrderItem::query()
            ->where('order_id', $orderId)
            ->where('status', '!=', OrderItemStatus::LOST->value)
            ->whereHas('resourceOrderItems', fn ($q) => $q
                ->whereHas('resource', fn ($q) => $q->where('clinic_department_id', $departmentId)))
            ->whereNotIn('id', $lastDocument->order_item_ids)
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
