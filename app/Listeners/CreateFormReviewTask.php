<?php

namespace App\Listeners;

use App\Actions\Activities\CreateActivityForLeadAction;
use App\Actions\Activities\CreateActivityForOrderAction;
use App\Actions\Activities\DuplicateException;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Events\PatientFormCompletedEvent;
use App\Models\AnamnesisGvlForm;
use App\Services\Anamnesis\AnamnesisOrderResolver;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Models\Lead;

class CreateFormReviewTask
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly CreateActivityForOrderAction $createActivityForOrderAction,
        private readonly CreateActivityForLeadAction $createActivityForLeadAction,
        private readonly AnamnesisOrderResolver $anamnesisOrderResolver,
    ) {}

    public function handle(PatientFormCompletedEvent $event): void
    {
        Log::info('Create activity to validate form, form has been filled in by user');

        $payload = [
            'type'          => ActivityType::TASK,
            'title'         => "{$event->formType->label()} controleren",
            'comment'       => "Patiënt heeft {$event->formType->label()} formulier ingevuld (ID: {$event->formId}).",
            'schedule_from' => now(),
            'schedule_to'   => now(),
            'is_done'       => false,
            'status'        => ActivityStatus::ACTIVE,
            'additional'    => [
                'form_id'  => $event->formId,
                'form_url' => config('services.portal.patient.web_url').'/patient/forms/'.$event->formId.'/step/1',
            ],
        ];

        $anamnesis = AnamnesisGvlForm::where('gvl_form_id', $event->formId)->first()?->anamnesis;
        if ($anamnesis === null) {
            Log::error('CreateFormReviewTask: geen anamnese gevonden voor GVL formulier', ['form_id' => $event->formId]);

            return;
        }

        $order = $this->anamnesisOrderResolver->findActiveOrderForAnamnesis($anamnesis);
        if ($order) {
            try {
                $this->createActivityForOrderAction->execute($order, false, $payload);
            } catch (DuplicateException $e) {
                Log::info('CreateFormReviewTask: duplicate order activity skipped', [
                    'order_id' => $order->id,
                    'form_id'  => $event->formId,
                ]);
            }

            return;
        }

        if ($anamnesis->lead_id) {
            $lead = Lead::find($anamnesis->lead_id);
            if ($lead) {
                try {
                    $this->createActivityForLeadAction->execute($lead, false, $payload);
                } catch (DuplicateException $e) {
                    Log::info('CreateFormReviewTask: duplicate lead activity skipped', [
                        'lead_id' => $lead->id,
                        'form_id' => $event->formId,
                    ]);
                }

                return;
            }
        }

        $this->activityRepository->create(array_merge($payload, [
            'person_id' => $event->person->id,
        ]));
    }
}
