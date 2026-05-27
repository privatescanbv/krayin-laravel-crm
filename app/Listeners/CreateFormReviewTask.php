<?php

namespace App\Listeners;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Events\PatientFormCompletedEvent;
use App\Models\Anamnesis;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;

class CreateFormReviewTask
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
    ) {}

    public function handle(PatientFormCompletedEvent $event): void
    {
        // Voor alle type formulier dit uitvoeren.
        //        if ($event->formType === FormType::PrivateScan) {
        Log::info('Create activity to validate form, form has been filled in by user');

        $payload = [
            'type'          => ActivityType::TASK,
            'title'         => "{$event->formType->label()} controleren",
            'comment'       => "Patiënt heeft {$event->formType->label()} formulier ingevuld (ID: {$event->formId}).",
            'person_id'     => $event->person->id,
            'schedule_from' => now(),
            'schedule_to'   => now(),
            'is_done'       => false,
            'status'        => ActivityStatus::ACTIVE,
            'additional'    => [
                'form_id'  => $event->formId,
                'form_url' => config('services.portal.patient.web_url').'/patient/forms/'.$event->formId.'/step/1',
            ],
        ];

        $anamnesis = Anamnesis::where('gvl_form_id', $event->formId)->first();
        if ($anamnesis === null) {
            Log::error('CreateFormReviewTask: geen anamnese gevonden voor GVL formulier', ['form_id' => $event->formId]);

            return;
        }

        if ($anamnesis->sales_id) {
            $order = Order::where('sales_lead_id', $anamnesis->sales_id)
                ->where(function ($q) {
                    $q->whereNull('pipeline_stage_id')
                        ->orWhereHas('stage', fn($s) => $s->where('is_won', false)->where('is_lost', false));
                })
                ->latest()
                ->first();

            if ($order) {
                $payload['order_id'] = $order->id;
            } else {
                //fall back to lead
                $payload['lead_id'] = $anamnesis->lead_id;
            }
        }

        $this->activityRepository->create($payload);
    }
}
