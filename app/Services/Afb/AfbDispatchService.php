<?php

namespace App\Services\Afb;

use App\Enums\AfbDispatchStatus;
use App\Enums\AfbDispatchType;
use App\Jobs\SendAfbDispatchJob;
use App\Models\AfbDispatch;
use App\Models\AfbDispatchOrder;
use App\Models\Clinic;
use App\Models\Order;
use App\Services\Mail\CrmMailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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
            ->whereDate('orders.first_examination_at', $date->toDateString())
            ->whereNotNull('resources.clinic_id')
            ->select('orders.id as order_id', 'resources.clinic_id')
            ->distinct()
            ->get();

        $groupedOrderIds = [];

        foreach ($pairs as $pair) {
            $clinicId = (int) $pair->clinic_id;
            $groupedOrderIds[$clinicId] ??= [];
            $groupedOrderIds[$clinicId][] = (int) $pair->order_id;
        }

        foreach ($groupedOrderIds as $clinicId => $orderIds) {
            $orderIds = array_values(array_unique($orderIds));
            SendAfbDispatchJob::dispatch($clinicId, $orderIds, AfbDispatchType::BATCH->value);
        }

        return count($groupedOrderIds);
    }

    public function queueLateBookingForOrder(Order $order): int
    {
        if (! $this->shouldSendAsLateBooking($order)) {
            return 0;
        }

        $queued = 0;
        $clinicIds = $this->getClinicIdsForOrder((int) $order->id);

        foreach ($clinicIds as $clinicId) {
            if ($this->isAlreadySentToClinic((int) $order->id, (int) $clinicId)) {
                continue;
            }

            SendAfbDispatchJob::dispatch((int) $clinicId, [(int) $order->id], AfbDispatchType::INDIVIDUAL->value);
            $queued++;
        }

        return $queued;
    }

    public function sendDispatch(
        int $clinicId,
        array $orderIds,
        AfbDispatchType $type,
        int $attempt = 1
    ): AfbDispatch {
        $clinic = Clinic::query()->findOrFail($clinicId);

        $orders = Order::query()
            ->whereIn('id', $orderIds)
            ->with([
                'salesLead.persons',
                'orderItems.resourceOrderItems.resource.clinic',
                'orderItems.person',
            ])
            ->get()
            ->keyBy('id');

        $unsentOrders = collect($orderIds)
            ->map(fn (int $id) => $orders->get($id))
            ->filter(fn (?Order $order) => $order !== null)
            ->filter(fn (Order $order) => ! $this->isAlreadySentToClinic((int) $order->id, $clinicId))
            ->values();

        $dispatch = AfbDispatch::create([
            'clinic_id'       => $clinicId,
            'type'            => $type->value,
            'status'          => AfbDispatchStatus::FAILED->value,
            'order_ids'       => $unsentOrders->pluck('id')->values()->all(),
            'attempt'         => max(1, $attempt),
            'last_attempt_at' => now(),
        ]);

        if ($unsentOrders->isEmpty()) {
            $dispatch->update([
                'status'   => AfbDispatchStatus::SUCCESS->value,
                'sent_at'  => now(),
            ]);

            return $dispatch;
        }

        try {
            $generatedDocuments = [];

            foreach ($unsentOrders as $order) {
                $generatedDocuments[] = [
                    'order' => $order,
                    ...$this->afbDocumentGenerator->generateForOrderAndClinic($order, $clinic),
                ];
            }

            $email = $this->createDispatchEmail(
                $clinic,
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

            foreach ($generatedDocuments as $generatedDocument) {
                AfbDispatchOrder::create([
                    'afb_dispatch_id' => $dispatch->id,
                    'order_id'        => $generatedDocument['order']->id,
                    'clinic_id'       => $clinicId,
                    'person_id'       => $generatedDocument['person_id'],
                    'patient_name'    => $generatedDocument['patient_name'],
                    'file_name'       => $generatedDocument['file_name'],
                    'file_path'       => $generatedDocument['file_path'],
                    'sent_at'         => now(),
                ]);

                $generatedDocument['order']->update([
                    'afb_sent_at'            => now(),
                    'afb_sent_type'          => $type->value,
                    'afb_sent_to_clinic_id'  => $clinicId,
                ]);
            }

            $dispatch->update([
                'email_id'  => $email->id,
                'status'    => AfbDispatchStatus::SUCCESS->value,
                'sent_at'   => now(),
            ]);

            Log::info('AFB dispatch success', [
                'clinic_id' => $clinicId,
                'order_ids' => $unsentOrders->pluck('id')->values()->all(),
                'timestamp' => now()->toIso8601String(),
                'type'      => $type->value,
                'status'    => AfbDispatchStatus::SUCCESS->value,
            ]);
        } catch (\Throwable $e) {
            $dispatch->update([
                'status'        => AfbDispatchStatus::FAILED->value,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('AFB dispatch failed', [
                'clinic_id'    => $clinicId,
                'order_ids'    => $unsentOrders->pluck('id')->values()->all(),
                'timestamp'    => now()->toIso8601String(),
                'type'         => $type->value,
                'status'       => AfbDispatchStatus::FAILED->value,
                'error_message'=> $e->getMessage(),
            ]);

            throw $e;
        }

        return $dispatch->refresh();
    }

    public function isAlreadySentToClinic(int $orderId, int $clinicId): bool
    {
        $order = Order::query()->find($orderId);

        if (
            $order
            && $order->afb_sent_at
            && (int) $order->afb_sent_to_clinic_id === $clinicId
        ) {
            return true;
        }

        return AfbDispatchOrder::query()
            ->where('order_id', $orderId)
            ->where('clinic_id', $clinicId)
            ->whereHas('dispatch', function ($query) {
                $query->where('status', AfbDispatchStatus::SUCCESS->value);
            })
            ->exists();
    }

    private function shouldSendAsLateBooking(Order $order): bool
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
     * @return array<int, int>
     */
    private function getClinicIdsForOrder(int $orderId): array
    {
        return DB::table('order_items')
            ->join('resource_orderitem', 'resource_orderitem.orderitem_id', '=', 'order_items.id')
            ->join('resources', 'resources.id', '=', 'resource_orderitem.resource_id')
            ->where('order_items.order_id', $orderId)
            ->whereNotNull('resources.clinic_id')
            ->pluck('resources.clinic_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function createDispatchEmail(
        Clinic $clinic,
        AfbDispatchType $type,
        array $orderNumbers,
        int $attachmentCount
    ): Email {
        $recipientEmails = $this->extractRecipientEmails($clinic);

        if (empty($recipientEmails)) {
            throw new RuntimeException('Kliniek heeft geen geldig emailadres voor AFB verzending.');
        }

        $subject = sprintf(
            'AFB %s - %s (%d bijlage%s)',
            $type === AfbDispatchType::BATCH ? 'batch' : 'individueel',
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
            'reply_to'  => $recipientEmails,
            'name'      => 'AFB verzending',
            'source'    => 'system',
            'user_type' => 'user',
            'clinic_id' => $clinic->id,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function extractRecipientEmails(Clinic $clinic): array
    {
        $emails = collect($clinic->emails ?? [])
            ->map(function ($email) {
                if (is_string($email)) {
                    return trim($email);
                }

                if (is_array($email)) {
                    return trim((string) ($email['value'] ?? ''));
                }

                return '';
            })
            ->filter(fn (string $email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        return $emails;
    }

    private function syncToMailDisk(string $path): void
    {
        if (Storage::exists($path)) {
            return;
        }

        $localDisk = Storage::disk('local');

        if ($localDisk->exists($path)) {
            Storage::put($path, $localDisk->get($path));
        }
    }
}
