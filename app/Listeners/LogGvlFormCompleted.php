<?php

namespace App\Listeners;

use App\Events\PatientFormCompletedEvent;
use App\Models\AnamnesisGvlForm;
use App\Services\Anamnesis\GvlAuditLogger;

class LogGvlFormCompleted
{
    public function __construct(private readonly GvlAuditLogger $logger) {}

    public function handle(PatientFormCompletedEvent $event): void
    {
        $form = AnamnesisGvlForm::where('gvl_form_id', $event->formId)->first();

        if ($form !== null) {
            $this->logger->log($form, 'afgerond');
        }
    }
}
