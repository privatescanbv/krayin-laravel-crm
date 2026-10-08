<?php

namespace App\Listeners;

use App\Enums\FormStatus;
use App\Events\PatientFormStatusUpdatedEvent;
use App\Models\AnamnesisGvlForm;
use Illuminate\Support\Facades\Log;

class UpdateAnamnesisFormStatus
{
    public function handle(PatientFormStatusUpdatedEvent $event): void
    {
        $columns = ['gvl_form_status' => $event->status];

        // Stamp the first moment a form reaches "completed"; keep it stable afterwards.
        if ($event->status === FormStatus::Completed) {
            AnamnesisGvlForm::where('gvl_form_id', $event->formId)
                ->whereNull('completed_at')
                ->update(['completed_at' => now()]);
        }

        $forms = AnamnesisGvlForm::where('gvl_form_id', $event->formId);

        // Check existence, not the update count: MySQL reports 0 affected rows when nothing changed
        // (a repeated webhook in the same second), which is not "not found".
        if (! $forms->exists()) {
            Log::error('UpdateAnamnesisFormStatus: geen anamnese gvl-formulier gevonden', [
                'form_id' => $event->formId,
                'status'  => $event->status->value,
            ]);

            return;
        }

        $forms->update($columns);
    }
}
