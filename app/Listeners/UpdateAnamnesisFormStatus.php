<?php

namespace App\Listeners;

use App\Events\PatientFormStatusUpdatedEvent;
use App\Models\Anamnesis;
use Illuminate\Support\Facades\Log;

class UpdateAnamnesisFormStatus
{
    public function handle(PatientFormStatusUpdatedEvent $event): void
    {
        $updated = Anamnesis::where('gvl_form_id', $event->formId)
            ->update(['gvl_form_status' => $event->status]);

        if ($updated === 0) {
            Log::error('UpdateAnamnesisFormStatus: geen anamnese gevonden voor formulier', [
                'form_id' => $event->formId,
                'status'  => $event->status->value,
            ]);
        }
    }
}
