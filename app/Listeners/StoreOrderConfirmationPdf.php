<?php

namespace App\Listeners;

use App\Enums\ActivityType;
use App\Enums\NotificationReferenceType;
use App\Events\OrderMarkedAsSent;
use App\Events\PatientNotifyEvent;
use App\Models\Order;
use App\Services\Storage\DocumentStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Core\Traits\PDFHandler;

class StoreOrderConfirmationPdf
{
    use PDFHandler;

    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly DocumentStorage $documentStorage
    ) {}

    public function handle(OrderMarkedAsSent $event): void
    {
        $order = $event->order;

        // When combine_order is false, per-person PDFs are created via the
        // confirmation wizard (markPersonAsSent). Skip the single-PDF path.
        if ($order->combine_order === false) {
            return;
        }

        if (empty($order->confirmation_letter_content)) {
            Log::warning('Ignoring PDF generation for order without confirmation letter content', [
                'order_id' => $order->id,
            ]);

            return;
        }

        try {
            $this->storeConfirmationPdfActivity($order, $event->userId);
        } catch (Throwable $e) {
            Log::error('Failed to create confirmation PDF activity', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function storeConfirmationPdfActivity(Order $order, ?int $userId): Activity
    {
        $html = mb_convert_encoding($order->confirmation_letter_content, 'HTML-ENTITIES', 'UTF-8');
        $pdfContent = Pdf::loadHTML($this->adjustArabicAndPersianContent($html))
            ->setPaper('A4')
            ->output();

        $activity = $this->activityRepository->create([
            'type'              => ActivityType::FILE,
            'title'             => 'Orderbevestiging PDF',
            'comment'           => 'Automatisch gegenereerde orderbevestiging',
            'is_done'           => true,
            'user_id'           => $userId,
            'order_id'          => $order->id,
            'additional'        => [
                'document_type' => 'order_confirmation',
            ],
        ]);

        $fileName = 'order-bevestiging-'.$order->id.'-'.date('Y-m-d').'.pdf';
        $filePath = 'activities/'.$activity->id.'/'.$fileName;
        $this->documentStorage->put($filePath, $pdfContent);

        $activity->files()->create([
            'name' => $fileName,
            'path' => $filePath,
        ]);

        $personIds = $order->salesLead?->persons()->pluck('persons.id')->toArray() ?? [];
        if (! empty($personIds)) {
            $activity->syncPortalPersons($personIds);
        }

        $this->notifyPatients($order, $activity, $userId);

        return $activity;
    }

    private function notifyPatients(Order $order, Activity $activity, ?int $userId): void
    {
        if (! $order->salesLead) {
            return;
        }

        $personIds = $order->salesLead->persons()->pluck('persons.id')->toArray();

        foreach ($personIds as $personId) {
            PatientNotifyEvent::dispatch(
                $personId,
                'Orderbevestiging #'.$order->id,
                NotificationReferenceType::FILE,
                $activity->id,
                true,
                $userId
            );
        }
    }
}
