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
        // Generate PDF content
        $html = mb_convert_encoding($order->confirmation_letter_content, 'HTML-ENTITIES', 'UTF-8');
        $pdfContent = Pdf::loadHTML($this->adjustArabicAndPersianContent($html))
            ->setPaper('A4')
            ->output();

        // Create the activity first to get an ID for the file path
        $activity = $this->activityRepository->create([
            'type'                => ActivityType::FILE,
            'title'               => 'Orderbevestiging PDF',
            'comment'             => 'Automatisch gegenereerde orderbevestiging',
            'is_done'             => true,
            'publish_to_portal' => true,
            'user_id'             => $userId,
            'order_id'            => $order->id,
            'additional'          => [
                'document_type' => 'order_confirmation',
            ],
        ]);

        // Store the PDF file
        $fileName = 'order-bevestiging-'.$order->id.'-'.date('Y-m-d').'.pdf';
        $filePath = 'activities/'.$activity->id.'/'.$fileName;
        $this->documentStorage->put($filePath, $pdfContent);

        // Create the file record linked to the activity
        $activity->files()->create([
            'name' => $fileName,
            'path' => $filePath,
        ]);

        // Notify all patients linked to the order
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
                'document',
                'Orderbevestiging beschikbaar',
                'Uw orderbevestiging is beschikbaar om te bekijken.',
                NotificationReferenceType::ACTIVITY,
                $activity->id,
                false,
                $userId
            );
        }
    }
}
