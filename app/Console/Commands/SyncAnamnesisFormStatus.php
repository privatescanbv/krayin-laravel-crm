<?php

namespace App\Console\Commands;

use App\Enums\FormStatus;
use App\Enums\FormType;
use App\Events\PatientFormCompletedEvent;
use App\Events\PatientFormStatusUpdatedEvent;
use App\Models\AnamnesisGvlForm;
use App\Services\FormService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Vangnet voor de forms-webhook (PUT /api/webhooks/event → EventWebhookController):
 * als die push door technische redenen niet aankomt, loopt de anamnese-status in de
 * CRM achter op de forms-app. Deze command pullt de status elk uur op uit
 * GET /api/forms/{id}/status en draait exact dezelfde business-logica als de webhook
 * door dezelfde events te dispatchen (PatientFormStatusUpdatedEvent, en bij 'completed'
 * ook PatientFormCompletedEvent).
 */
class SyncAnamnesisFormStatus extends Command
{
    private const CHUNK_SIZE = 50;

    protected $signature = 'forms:sync-anamnesis-status';

    protected $description = 'Haal de anamnese/GVL-formulierstatus op uit de forms-app en werk hem bij (vangnet voor de webhook)';

    public function handle(FormService $formService): int
    {
        $checked = 0;
        $changed = 0;
        $completed = 0;
        $errors = 0;

        AnamnesisGvlForm::query()
            ->whereNotNull('gvl_form_id')
            ->where(function ($query) {
                $query->whereNull('gvl_form_status')
                    ->orWhere('gvl_form_status', '!=', FormStatus::Completed->value);
            })
            ->with('anamnesis.person')
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($forms) use ($formService, &$checked, &$changed, &$completed, &$errors) {
                foreach ($forms as $form) {
                    $checked++;

                    try {
                        $status = $formService->getFormStatusAsString((int) $form->gvl_form_id);
                    } catch (Throwable $e) {
                        $errors++;
                        Log::warning('forms:sync-anamnesis-status: kon status niet ophalen', [
                            'anamnesis_gvl_form_id' => $form->id,
                            'gvl_form_id'           => $form->gvl_form_id,
                            'error'                 => $e->getMessage(),
                        ]);

                        continue;
                    }

                    if ($status === $form->gvl_form_status) {
                        continue;
                    }

                    $changed++;
                    $formType = $form->gvl_form_type ?? FormType::PrivateScan;

                    // Zelfde events als EventWebhookController: de listeners doen het werk.
                    PatientFormStatusUpdatedEvent::dispatch($form->gvl_form_id, $status, $formType);

                    if ($status === FormStatus::Completed) {
                        $person = $form->anamnesis?->person;

                        if ($person !== null) {
                            $completed++;
                            PatientFormCompletedEvent::dispatch($person, $form->gvl_form_id, $formType);
                        } else {
                            Log::warning('forms:sync-anamnesis-status: geen person voor completed form, review-taak overgeslagen', [
                                'anamnesis_gvl_form_id' => $form->id,
                                'gvl_form_id'           => $form->gvl_form_id,
                                'anamnesis_id'          => $form->anamnesis_id,
                            ]);
                        }
                    }
                }
            });

        $this->info("Anamnese form-status sync klaar: {$checked} gecontroleerd, {$changed} bijgewerkt, {$completed} afgerond, {$errors} fout(en).");

        return self::SUCCESS;
    }
}
