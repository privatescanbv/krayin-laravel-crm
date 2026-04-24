<?php

namespace App\Services\Mail;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Email\Enums\SupportedFolderEnum;
use Webkul\Email\Models\Email;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;

class GraphMailService extends AbstractEmailProcessor
{
    protected string $baseUrl = 'https://graph.microsoft.com/v1.0';

    protected string $mailbox;

    public function __construct(
        EmailRepository $emailRepository,
        AttachmentRepository $attachmentRepository,
        private readonly MicrosoftGraphTokenService $tokenService,
    ) {
        parent::__construct($emailRepository, $attachmentRepository);
        $this->mailbox = config('mail.graph.mailbox');
    }

    // Abstract method implementations

    protected function fetchMessages(): array
    {
        $accessToken = $this->tokenService->getAccessToken();

        $url = "{$this->baseUrl}/users/{$this->mailbox}/mailFolders('Inbox')/messages";

        if (config('mail.graph.mailbox.read_new_mail_filter') == 'multi_environments') {
            $since = now()->subDays(1)->toIso8601String();
            $filter = "receivedDateTime ge {$since}";
        } else {
            $filter = 'isRead eq false';
        }

        $response = Http::withToken($accessToken)
            ->get($url, [
                '$filter'  => $filter,
                '$select'  => 'id,subject,from,toRecipients,ccRecipients,bccRecipients,receivedDateTime,isRead,hasAttachments,body,attachments,internetMessageId,conversationId,replyTo',
                '$orderby' => 'receivedDateTime desc',
                '$top'     => 50,
            ]);

        if (! $response->successful()) {
            throw new Exception('Failed to fetch messages: '.$response->body());
        }

        return $response->json('value') ?? [];
    }

    protected function isValidMessage($message): bool
    {
        return is_array($message) && isset($message['id']);
    }

    protected function getMessageId($message): string
    {
        return $message['internetMessageId'] ?? $message['id'];
    }

    protected function getConversationId($message): ?string
    {
        return $message['conversationId'] ?? null;
    }

    protected function getFolderName($message): string
    {
        return SupportedFolderEnum::INBOX->value;
    }

    protected function extractEmailData($message, string $folderName, ?Email $parentEmail): array
    {
        $from         = $message['from']['emailAddress'] ?? [];
        $toRecipients = $message['toRecipients'] ?? [];
        $ccRecipients = $message['ccRecipients'] ?? [];
        $bccRecipients = $message['bccRecipients'] ?? [];
        $replyTo      = $message['replyTo'] ?? [];

        $body      = $this->extractMessageBody($message);
        $fromEmail = $from['address'] ?? '';
        $fromName  = $from['name'] ?? null;

        return [
            'from'          => Email::normalizeFromField($fromEmail, $fromName),
            'subject'       => $message['subject'] ?? '',
            'name'          => $fromName ?? '',
            'reply'         => $body,
            'is_read'       => (int) ($message['isRead'] ?? false),
            'folder_id'     => $this->getFolderId($folderName),
            'reply_to'      => $this->extractEmailAddresses($replyTo),
            'cc'            => $this->extractEmailAddresses($ccRecipients),
            'bcc'           => $this->extractEmailAddresses($bccRecipients),
            'source'        => 'email',
            'user_type'     => 'person',
            'unique_id'     => $this->getMessageId($message),
            'message_id'    => $this->getMessageId($message),
            'reference_ids' => [$this->getMessageId($message)],
            'created_at'    => $this->parseDateTime($message['receivedDateTime'] ?? now()),
            'parent_id'     => $parentEmail?->id,
            'activity_id'   => $parentEmail?->activity_id,
            'lead_id'       => $parentEmail?->lead_id,
            'person_id'     => $parentEmail?->person_id,
        ];
    }

    protected function getFromEmail($message): string
    {
        return $message['from']['emailAddress']['address'] ?? '';
    }

    protected function getToRecipients($message): array
    {
        return $message['toRecipients'] ?? [];
    }

    protected function extractEmailFromRecipient($recipient): ?string
    {
        return $recipient['emailAddress']['address'] ?? null;
    }

    protected function hasAttachments($message): bool
    {
        return $message['hasAttachments'] ?? false;
    }

    protected function processAttachments(Email $email, $message): void
    {
        try {
            $accessToken = $this->tokenService->getAccessToken();
            $messageId   = $message['id'];
            $url         = "{$this->baseUrl}/users/{$this->mailbox}/messages/{$messageId}/attachments";

            $response = Http::withToken($accessToken)->get($url);

            if ($response->successful()) {
                foreach ($response->json('value') ?? [] as $attachment) {
                    $this->attachmentRepository->create([
                        'email_id'     => $email->id,
                        'name'         => $attachment['name'] ?? 'attachment',
                        'content_type' => $attachment['contentType'] ?? 'application/octet-stream',
                        'size'         => $attachment['size'] ?? 0,
                        'content'      => base64_decode($attachment['contentBytes'] ?? ''),
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to process attachments', [
                'email_id'   => $email->id,
                'message_id' => $message['id'],
                'error'      => $e->getMessage(),
            ]);
        }
    }

    protected function markMessageAsRead($message): void
    {
        try {
            $url = "{$this->baseUrl}/users/{$this->mailbox}/messages/{$message['id']}";

            Http::withToken($this->tokenService->getAccessToken())
                ->patch($url, ['isRead' => true]);
        } catch (Exception $e) {
            Log::error('Failed to mark message as read', [
                'message_id' => $message['id'],
                'error'      => $e->getMessage(),
            ]);
        }
    }

    protected function getSyncType(): string
    {
        return 'graph';
    }

    protected function getProcessorName(): string
    {
        return 'Microsoft Graph';
    }

    protected function getSyncMetadata(): array
    {
        return [
            'mailbox' => $this->mailbox,
        ];
    }

    // Helpers specific to Microsoft Graph

    /**
     * Extract message body from Graph response.
     *
     * Note: we intentionally return the content regardless of contentType — the CRM stores HTML
     * bodies as-is and plain text is stored without conversion. Keeping both branches explicit
     * here makes future differentiation straightforward.
     */
    protected function extractMessageBody(array $message): string
    {
        $body = $message['body'] ?? [];

        if (isset($body['contentType']) && $body['contentType'] === 'html') {
            return $body['content'] ?? '';
        }

        return $body['content'] ?? '';
    }

    /**
     * Extract a plain list of email addresses from a Graph recipients array.
     */
    protected function extractEmailAddresses(array $recipients): array
    {
        return collect($recipients)
            ->map(fn ($recipient) => $recipient['emailAddress']['address'] ?? '')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Parse a Graph DateTime string into a Carbon instance in the application timezone.
     */
    protected function parseDateTime(string $dateTime): Carbon
    {
        return Carbon::parse($dateTime)->setTimezone(config('app.timezone', 'UTC'));
    }
}
