<?php

namespace App\Listeners;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\FormType;
use App\Events\PatientFormCompletedEvent;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;

class CreateFormReviewTask
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
    ) {}

    public function handle(PatientFormCompletedEvent $event): void
    {
        if ($event->formType === FormType::PrivateScan) {
            Log::info('Create activity to validate form, form has been filled in by user');
            $this->activityRepository->create([
                'type'          => ActivityType::TASK,
                'title'         => 'Formulier controleren',
                'comment'       => "Patiënt heeft formulier ingevuld (ID: {$event->formId}).",
                'person_id'     => $event->person->id,
                'schedule_from' => now(),
                'schedule_to'   => now()->addDays(5),
                'is_done'       => false,
                'status'        => ActivityStatus::ACTIVE,
                'additional'    => [
                    'form_id' => $event->formId,
                    'form_url' => config('services.portal.patient.web_url')."/patient/forms/".$event->formId."/step/1"
                ],
            ]);
        } else {
            Log::info('Form filled in by user, no actions for this form type: '.$event->formType->value);
        }
    }
}
