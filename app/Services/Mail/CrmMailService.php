<?php

namespace App\Services\Mail;

use App\Enums\EmailTemplateCode;
use App\Enums\PersonPreferenceKey;
use App\Models\PersonPreference;
use Exception;
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
    public function renderTemplate(EmailTemplateCode $templateIdentifier, array $variables = []): array
    {
        $template = EmailTemplate::byCodeEnum($templateIdentifier)->firstOrFail();

        if (! $template) {
            throw new RuntimeException("Email template '{$templateIdentifier->value}' not found.");
        }

        $rendered = $this->templateRendering->render($template, $variables);

        return [
            'subject'  => $rendered['subject'],
            'html'     => $rendered['html'],
            'template' => $template,
        ];
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
     * Render a template (looked up by code or name) with entity-resolved variables.
     *
     * @param  array<string, mixed>  $entities  e.g. ['order' => 42, 'person' => 7]
     * @return array{subject: string, html: string}
     *
     * @throws RuntimeException when template not found
     */
    public function renderHtmlForEntities(string $codeOrName, array $entities): array
    {
        $template = EmailTemplate::byCode($codeOrName)
            ->orWhere('name', $codeOrName)
            ->first();

        if (! $template) {
            throw new RuntimeException("Email template '{$codeOrName}' not found.");
        }

        $variables = $this->templateRendering->resolveVariablesFromEntities($entities);
        $html = $this->templateRendering->renderTemplateToHTML($template, $variables);
        $subject = $this->templateRendering->interpolateTemplate($template->subject, $variables);

        return ['subject' => $subject, 'html' => $html];
    }

    /**
     * Render a template and send it to a person.
     *
     * @param  array{lead_id?: string|int|null, sales_lead_id?: string|int|null}  $linkEmailToEntities
     * @return true if email has been sent, otherwise false on failure
     */
    public function sendToPersonTemplate(
        Person $person,
        EmailTemplateCode $templateIdentifier,
        array $variables = [],
        array $linkEmailToEntities = [],
        bool $isNotify = true
    ): bool {
        if ($isNotify) {
            $emailEnabled = PersonPreference::getValueForPerson(
                $person->id,
                PersonPreferenceKey::EMAIL_NOTIFICATIONS_ENABLED
            );

            if (! $emailEnabled) {
                Log::info('Skipping email notification: person has disabled email notifications', [
                    'person_id' => $person->id,
                    'template'  => $templateIdentifier->value,
                ]);

                return false;
            }
        }
        $rendered = $this->renderTemplate($templateIdentifier, $variables);

        return $this->sendToPersonHtml(
            $person,
            $rendered['subject'],
            $rendered['html'],
            $linkEmailToEntities
        );
    }

    /**
     * Store + queue a system email to a person (patient/customer).
     *
     * @return true if email has been send, otherwise false on failure (e.g. no email address)
     */
    private function sendToPersonHtml(
        Person $person,
        string $subject,
        string $htmlContent,
        array $linkEmailToEntities
    ): bool {
        try {
            $recipientEmail = $person->findDefaultEmailOrError();
        } catch (Exception $e) {
            Log::error('Failed to send email: person has no default email address', [
                'person_id' => $person->id,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }

        $data = [
            'subject'   => $subject,
            'reply'     => $htmlContent,
            'reply_to'  => [$recipientEmail], // recipient (stored in reply_to for system emails)
            'name'      => $person->name,
            'source'    => 'system',
            'user_type' => 'user',
            'person_id' => $person->id,
        ];
        $email = $this->emailRepository->createWith($data, $linkEmailToEntities);

        Log::info('Sending CRM email to person', [
            'person_id' => $person->id,
            'email_id'  => $email->id,
            'to'        => $recipientEmail,
            'subject'   => $subject,
        ]);

        Mail::to($recipientEmail)->queue(new EmailMailable($email));

        return true;
    }
}
