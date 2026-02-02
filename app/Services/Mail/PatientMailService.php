<?php

namespace App\Services\Mail;

use Exception;
use Webkul\Contact\Models\Person;

class PatientMailService
{
    public function __construct(
        protected CrmMailService $crmMailService
    ) {}

    /**
     * Send email to patient and store in database.
     * Uses the same EmailRepository logic as EmailController for consistency.
     *
     * @throws Exception when all related entity IDs are null
     */
    public function mailPatient(
        Person $person,
        string $subject,
        string $htmlContent,
        ?string $relatedLeadId = null,
        ?string $relatedSalesId = null
    ): bool {
        $this->crmMailService->sendToPersonHtml($person, $subject, $htmlContent, [
            'lead_id'       => $relatedLeadId,
            'sales_lead_id' => $relatedSalesId,
        ]);

        return true;
    }
}
