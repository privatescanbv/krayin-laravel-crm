<?php

namespace App\Services\Mail;

use App\Enums\PersonPreferenceKey;
use App\Models\PersonPreference;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Webkul\Contact\Models\Person;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Mails\Email as EmailMailable;
use Webkul\Email\Models\Email as EmailModel;
use Webkul\Email\Models\Folder;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\EmailTemplate\Models\EmailTemplate;

/**
 * Single entry-point for application email functionality.
 *
 * - Renders EmailTemplate (DB) to final HTML + subject
 * - Stores email records via EmailRepository
 * - Queues Webkul\Email\Mails\Email for actual sending
 *
 * This service is meant to unify behavior across PatientMailService and order flows.
 */
class CrmMailService
{
    public function __construct(
        private readonly EmailRepository $emailRepository,
        private readonly EmailTemplateRenderingService $templateRendering,
    ) {}

    /**
     * Render a DB email template (code or name).
     *
     * @return array{subject: string, html: string, template: EmailTemplate}
     */
    public function renderTemplate(string $templateIdentifier, array $variables = []): array
    {
        $template = EmailTemplate::query()
            ->where('code', $templateIdentifier)
            ->orWhere('name', $templateIdentifier)
            ->first();

        if (! $template) {
            throw new RuntimeException("Email template '{$templateIdentifier}' not found.");
        }

        $rendered = $this->templateRendering->render($template, $variables);

        return [
            'subject'  => $rendered['subject'],
            'html'     => $rendered['html'],
            'template' => $template,
        ];
    }

    /**
     * Store + queue a system email to a person (patient/customer).
     *
     * @param  array{lead_id?: string|int|null, sales_lead_id?: string|int|null}  $links
     */
    public function sendToPersonHtml(Person $person, string $subject, string $htmlContent, array $links = []): EmailModel
    {
        $recipientEmail = $person->findDefaultEmailOrError();

        $data = [
            'subject'   => $subject,
            'reply'     => $htmlContent,
            'reply_to'  => [$recipientEmail], // recipient (stored in reply_to for system emails)
            'name'      => $person->name,
            'source'    => 'system',
            'user_type' => 'user',
            'person_id' => $person->id,
        ];

        if (array_key_exists('lead_id', $links) && $links['lead_id'] !== null) {
            $data['lead_id'] = (string) $links['lead_id'];
        } elseif (array_key_exists('sales_lead_id', $links) && $links['sales_lead_id'] !== null) {
            $data['sales_lead_id'] = (string) $links['sales_lead_id'];
        }

        $email = $this->emailRepository->create($data);

        Log::info('Sending CRM email to person', [
            'person_id' => $person->id,
            'email_id'  => $email->id,
            'to'        => $recipientEmail,
            'subject'   => $subject,
        ]);

        Mail::to($recipientEmail)->queue(new EmailMailable($email));

        return $email;
    }

    /**
     * Create an email record (same pathway as Admin mail UI).
     */
    public function createEmail(array $data): EmailModel
    {
        return $this->emailRepository->create($data);
    }

    /**
     * Send an already created email and optionally move it to a folder.
     */
    public function sendEmail(EmailModel $email, EmailFolderEnum|string|null $folderAfterSend = EmailFolderEnum::SENT): void
    {
        Mail::send(new EmailMailable($email));

        if ($folderAfterSend) {
            $folderName = $folderAfterSend instanceof EmailFolderEnum
                ? $folderAfterSend->getFolderName()
                : $folderAfterSend;

            $folder = Folder::where('name', $folderName)->first();

            if ($folder) {
                $this->emailRepository->update([
                    'folder_id' => $folder->id,
                ], $email->id);
            }
        }
    }

    /**
     * Create + optionally send (used by Admin mail UI).
     */
    public function createAndMaybeSend(array $data, bool $isDraft, EmailFolderEnum|string|null $folderAfterSend = EmailFolderEnum::SENT): EmailModel
    {
        $email = $this->createEmail($data);

        if (! $isDraft) {
            $this->sendEmail($email, $folderAfterSend);
        }

        return $email;
    }

    /**
     * Render a template and send it to a person.
     *
     * @param  array{lead_id?: string|int|null, sales_lead_id?: string|int|null}  $links
     */
    public function sendToPersonTemplate(Person $person, string $templateIdentifier, array $variables = [], array $links = [], bool $isNotify = true): ?EmailModel
    {
        if ($isNotify) {
            $emailEnabled = PersonPreference::getValueForPerson(
                $person->id,
                PersonPreferenceKey::EMAIL_NOTIFICATIONS_ENABLED
            );

            if (! $emailEnabled) {
                Log::info('Skipping email notification: person has disabled email notifications', [
                    'person_id' => $person->id,
                    'template'  => $templateIdentifier,
                ]);

                return null;
            }
        }

        $rendered = $this->renderTemplate($templateIdentifier, $variables);

        return $this->sendToPersonHtml(
            $person,
            $rendered['subject'],
            $rendered['html'],
            $links
        );
    }
}
