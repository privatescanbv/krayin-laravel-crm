<?php

namespace App\Services\Mail;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;

/**
 * Inbound email processor for Microsoft Graph (Office 365 / Exchange Online).
 *
 * Fetches unread messages from the configured mailbox via the Graph REST API,
 * normalises them into the application's Email model, stores attachments, and
 * marks each message as read once processed.
 *
 * Supports multiple mailboxes via {@see configureMailbox()}.
 *
 * Bound to the {@see InboundEmailProcessor} contract when the
 * `mail-receiver.default` config value is `'microsoft-graph'`.
 *
 * Authentication uses the OAuth 2.0 client-credentials flow delegated to
 * {@see MicrosoftGraphTokenService}.
 */
class GraphMailService extends AbstractEmailProcessor
{
    protected string $baseUrl = 'https://graph.microsoft.com/v1.0';

    protected string $mailbox;

    protected string $mailboxKey;

    protected string $inboxFolderName;

    public function __construct(
        EmailRepository $emailRepository,
        AttachmentRepository $attachmentRepository,
        EmailEntityLinker $emailEntityLinker,
        private readonly MicrosoftGraphTokenService $tokenService,
    ) {
        parent::__construct($emailRepository, $attachmentRepository, $emailEntityLinker);

        $this->mailboxKey = MailboxConfig::defaultKey() ?? 'privatescan';
        $this->mailbox = MailboxConfig::address($this->mailboxKey) ?? '';
        $this->inboxFolderName = MailboxConfig::get($this->mailboxKey)['folder_name']
            ?? EmailFolderEnum::INBOX->value;
    }

    /**
     * Configure the service to operate on a specific mailbox.
     *
     * @param  string  $mailboxAddress  The Exchange mailbox address (e.g. service@herniapoli.nl)
     * @param  string  $mailboxKey  Identifier stored on Email records (e.g. 'herniapoli')
     * @param  string|null  $folderName  Inbox folder name in the local folders table
     */
    public function configureMailbox(string $mailboxAddress, string $mailboxKey, ?string $folderName = null): void
    {
        $this->mailbox = $mailboxAddress;
        $this->mailboxKey = $mailboxKey;
        $this->inboxFolderName = $folderName
            ?? MailboxConfig::get($mailboxKey)['folder_name']
            ?? EmailFolderEnum::INBOX->value;
    }

    protected function fetchMessages(): array
    {
        $accessToken = $this->tokenService->getAccessToken($this->mailboxKey);

        $url = "{$this->baseUrl}/users/{$this->mailbox}/mailFolders('Inbox')/messages";

        if (config('mail.mailers.microsoft-graph.read_new_mail_filter') === 'multi_environments') {
            $since = now()->subDays(1)->toIso8601String();
            $filter = "receivedDateTime ge {$since}";
        } else {
            $filter = 'isRead eq false';
        }

        $response = Http::withToken($accessToken)
            ->get($url, [
                '$filter'  => $filter,
                '$select'  => 'id,subject,from,toRecipients,ccRecipients,bccRecipients,receivedDateTime,isRead,hasAttachments,body,attachments,internetMessageId,conversationId,replyTo,internetMessageHeaders',
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

    protected function getInReplyToId($message): ?string
    {
        foreach ($message['internetMessageHeaders'] ?? [] as $header) {
            if (strtolower($header['name'] ?? '') === 'in-reply-to') {
                return trim($header['value'] ?? '') ?: null;
            }
        }

        return null;
    }

    protected function getFolderName($message): string
    {
        return $this->inboxFolderName;
    }

    protected function extractEmailData($message, string $folderName, ?Email $parentEmail): array
    {
        $from = $message['from']['emailAddress'] ?? [];
        $toRecipients = $message['toRecipients'] ?? [];
        $ccRecipients = $message['ccRecipients'] ?? [];
        $bccRecipients = $message['bccRecipients'] ?? [];
        $replyTo = $message['replyTo'] ?? [];

        $body = $this->extractMessageBody($message);
        $fromEmail = $from['address'] ?? '';
        $fromName = $from['name'] ?? null;

        return [
            'from'          => Email::normalizeFromField($fromEmail, $fromName),
            'subject'       => $message['subject'] ?? '',
            'name'          => $fromName ?? '',
            'reply'         => $body,
            'is_read'       => (int) ($message['isRead'] ?? false),
            'folder_id'     => $this->getFolderId($folderName),
            'mailbox_key'   => $this->mailboxKey,
            'reply_to'      => $this->extractEmailAddresses($replyTo),
            'cc'            => $this->extractEmailAddresses($ccRecipients),
            'bcc'           => $this->extractEmailAddresses($bccRecipients),
            'source'        => 'email',
            'user_type'     => 'person',
            'unique_id'     => $this->getMessageId($message),
            'message_id'    => $this->getMessageId($message),
            'reference_ids' => array_values(array_filter(array_unique([
                $this->getMessageId($message),
                $message['conversationId'] ?? null,
            ]))),
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
            $accessToken = $this->tokenService->getAccessToken($this->mailboxKey);
            $messageId = $message['id'];
            $url = "{$this->baseUrl}/users/{$this->mailbox}/messages/{$messageId}/attachments";

            $response = Http::withToken($accessToken)->get($url);

            if ($response->successful()) {
                foreach ($response->json('value') ?? [] as $attachment) {
                    $this->attachmentRepository->createFromGraphData($email, $attachment);
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

            Http::withToken($this->tokenService->getAccessToken($this->mailboxKey))
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
            'mailbox'     => $this->mailbox,
            'mailbox_key' => $this->mailboxKey,
        ];
    }

    protected function extractMessageBody(array $message): string
    {
        $body = $message['body'] ?? [];

        if (isset($body['contentType']) && $body['contentType'] === 'html') {
            return $body['content'] ?? '';
        }

        return $body['content'] ?? '';
    }

    protected function extractEmailAddresses(array $recipients): array
    {
        return collect($recipients)
            ->map(fn ($recipient) => $recipient['emailAddress']['address'] ?? '')
            ->filter()
            ->values()
            ->toArray();
    }

    protected function parseDateTime(string $dateTime): Carbon
    {
        return Carbon::parse($dateTime)->setTimezone(config('app.timezone', 'UTC'));
    }
}
