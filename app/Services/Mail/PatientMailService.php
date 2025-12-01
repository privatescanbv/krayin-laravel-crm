<?php

namespace App\Services\Mail;

use Exception;
use Illuminate\Support\Facades\Mail;
use Webkul\Contact\Models\Person;
use Webkul\Email\Mails\Email as EmailMailable;
use Webkul\Email\Repositories\EmailRepository;

class PatientMailService
{
    public function __construct(
        protected EmailRepository $emailRepository
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
        $recipientEmail = $person->findDefaultEmailOrError();

        // Prepare data for EmailRepository (same format as EmailController)
        // Note: from field normalization and entity linking prioritization is handled by EmailRepository::create()
        $data = [
            'subject'   => $subject,
            'reply'     => $htmlContent,
            'reply_to'  => [$recipientEmail], // recipient (stored in reply_to for system emails)
            'name'      => $person->name,
            'source'    => 'system',
            'user_type' => 'user',
        ];
        if (! is_null($relatedLeadId)) {
            $data['lead_id'] = $relatedLeadId;
        } elseif (! is_null($relatedSalesId)) {
            $data['sales_lead_id'] = $relatedSalesId;
        }
        $data['person_id'] = $person->id;

        // Create email record using EmailRepository (same as EmailController)
        $email = $this->emailRepository->create($data);

        // Send email using same method as EmailController
        Mail::to($recipientEmail)->queue(new EmailMailable($email));

        return true;
    }
}
