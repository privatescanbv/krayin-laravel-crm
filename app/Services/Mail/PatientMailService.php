<?php

namespace App\Services\Mail;

use Exception;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email as EmailModel;

class PatientMailService
{
    /**
     * @throws Exception when all related entity IDs are null
     */
    public function mailPatient(
        Person $person,
        Mailable $mail,
        ?string $relatedLeadId = null,
        ?string $relatedSalesId = null,
        ?string $relatedPersonId = null): bool
    {
        $emailAddress = $person->findDefaultEmail();

        if (! $emailAddress) {
            logger()->error('Could not send e-mail to person. No email address found for person', [
                'person_id'     => $person->id,
                'person_name'   => $person->name,
            ]);

            return false;
        }

        $this->storeEmailRecord($person, $mail, $relatedLeadId, $relatedSalesId, $relatedPersonId);

        return true;
    }

    private function storeEmailRecord(
        Person $person,
        Mailable $mail,
        ?string $relatedLeadId = null,
        ?string $relatedSalesId = null,
        ?string $relatedPersonId = null

    ): void {
        // Build the mailable to get subject and view data
        $builtMail = $mail->build();

        // Get subject from the mailable
        $subject = $builtMail->subject ?? 'No subject';

        // Render the email body (HTML content) from the view
        $view = $builtMail->view;
        // Handle array views (e.g., ['html' => 'view', 'text' => 'view'])
        if (is_array($view)) {
            $view = $view['html'] ?? $view[0] ?? null;
        }

        // Get view data using reflection to access protected viewData property
        $viewData = [];
        try {
            $reflection = new ReflectionClass($builtMail);
            $viewDataProperty = $reflection->getProperty('viewData');
            $viewDataProperty->setAccessible(true);
            $viewData = $viewDataProperty->getValue($builtMail) ?? [];
        } catch (ReflectionException $e) {
            // Fallback: if reflection fails, try to get data from the mailable
            // For PortalGVLCompletedMail, we know the data structure
            if (method_exists($builtMail, 'with')) {
                $viewData = [];
            }
        }

        $body = $view ? View::make($view, $viewData)->render() : '';

        // Get from address from config or mailable
        $fromAddress = config('mail.from.address', 'no-reply@privatescan.nl');
        $fromName = config('mail.from.name', 'PrivateScan');

        // Normalize from field to standard format
        $from = EmailModel::normalizeFromField($fromAddress, $fromName);

        // Get recipient email address (person's email at the time of sending)
        // This is stored in reply_to for system emails (where reply_to represents the recipient)
        $recipientEmail = $person->findDefaultEmail();
        $replyTo = $recipientEmail ? [$recipientEmail] : [];

        $emailModel = new EmailModel;
        $emailModel->subject = $subject;
        $emailModel->reply = $body;
        $emailModel->from = $from;
        $emailModel->reply_to = $replyTo;
        $emailModel->name = $person->name;
        $emailModel->person_id = $person->id;
        $emailModel->source = 'system';
        $emailModel->user_type = 'user';
        $emailModel->message_id = (string) Str::uuid();

        if (! is_null($relatedLeadId)) {
            $emailModel->lead_id = $relatedLeadId;
        } elseif (! is_null($relatedSalesId)) {
            $emailModel->sales_lead_id = $relatedSalesId;
        } elseif (! is_null($relatedPersonId)) {
            $emailModel->person_id = $relatedPersonId;
        } else {
            throw new Exception('At least one related entity ID must be provided.');
        }

        $emailModel->save();
    }
}
