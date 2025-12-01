<?php

namespace App\Actions\Forms;

use App\Mail\PortalGVLCompletedMail;
use App\Services\FormService;
use App\Services\Mail\PatientMailService;
use RuntimeException;
use Webkul\Contact\Repositories\PersonRepository;

class FormUpdatedAction
{
    public function __construct(
        private readonly PatientMailService $emailService,
        private readonly FormService $formService,
        private readonly PersonRepository $personRepository
    ) {}

    /**
     * @return array{success: bool, message?: string}
     */
    public function execute(string $formId, string $status, string $showFormUrl): array
    {
        // , string $formUrl
        logger()->info('Form response status updated', [
            'form_id' => $formId,
            'status'  => $status,
        ]);
        if ($status == 'completed') {
            $this->sendPatientNotify($showFormUrl);

            // TODO send more notifications or add activity for employee?
        }

        return [
            'success' => true,
            'message' => '3',
        ];
    }

    /**
     * Verstuur de welkomstmail en koppel deze aan lead/person in de email‑historie.
     */
    protected function sendPatientNotify(string $showFromUrl): void
    {
        $related = $this->formService->findRelatedEntityByFormId($showFromUrl);

        $leadId = $related['lead']?->id ?? null;
        $salesId = $related['sales']?->id ?? null;
        $personId = $related['person_id'] ?? null;

        if (! $personId) {
            throw new RuntimeException('Geen persoon gekoppeld aan het formulier, e-mail kan niet worden verstuurd.');
        }

        $person = $this->personRepository->find($personId)
            ?? throw new RuntimeException("Persoon met ID {$personId} niet gevonden.");

        $this->emailService->mailPatient(
            $person,
            new PortalGVLCompletedMail($person, $showFromUrl),
            $leadId, $salesId, $personId
        );
    }
}
