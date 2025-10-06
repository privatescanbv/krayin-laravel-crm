<?php

namespace App\Services\Mail;

use App\Models\EmailLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Email\Enums\SupportedFolderEnum;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Email\Models\Email;

class GraphMailService
{
    protected string $accessToken;
    protected string $baseUrl = 'https://graph.microsoft.com/v1.0';
    protected string $mailbox;
    protected ?EmailLog $currentLog = null;

    public function __construct(
        protected EmailRepository $emailRepository,
        protected AttachmentRepository $attachmentRepository
    ) {
        $this->mailbox = config('mail.graph.mailbox');
    }

    /**
     * Get access token using client credentials flow
     */
    protected function getAccessToken(): string
    {
        if (isset($this->accessToken)) {
            return $this->accessToken;
        }

        $tenantId = config('mail.graph.tenant_id');
        $clientId = config('mail.graph.client_id');
        $clientSecret = config('mail.graph.client_secret');

        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ]);

        if (!$response->successful()) {
            throw new Exception('Failed to get access token: ' . $response->body());
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'];

        return $this->accessToken;
    }

    /**
     * Process messages from Microsoft Graph
     */
    public function processMessagesFromAllFolders(): void
    {
        try {
            $this->logSyncStart();
            
            $messages = $this->fetchUnreadMessages();
            $processedCount = 0;
            $errorCount = 0;

            foreach ($messages as $message) {
                try {
                    $this->processMessage($message);
                    $processedCount++;
                } catch (Exception $e) {
                    $errorCount++;
                    Log::error('Failed to process message', [
                        'message_id' => $message['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->logSyncComplete($processedCount, $errorCount);

        } catch (Exception $e) {
            $this->logSyncError($e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch unread messages from Microsoft Graph
     */
    protected function fetchUnreadMessages(): array
    {
        $accessToken = $this->getAccessToken();
        $mailbox = $this->mailbox;
        
        $url = "{$this->baseUrl}/users/{$mailbox}/mailFolders('Inbox')/messages";
        
        $response = Http::withToken($accessToken)
            ->get($url, [
                '$filter' => 'isRead eq false',
                '$select' => 'id,subject,from,toRecipients,ccRecipients,bccRecipients,receivedDateTime,isRead,hasAttachments,body,attachments,internetMessageId,conversationId,replyTo',
                '$orderby' => 'receivedDateTime desc',
                '$top' => 50 // Limit to prevent timeout
            ]);

        if (!$response->successful()) {
            throw new Exception('Failed to fetch messages: ' . $response->body());
        }

        $data = $response->json();
        return $data['value'] ?? [];
    }

    /**
     * Process a single message
     */
    public function processMessage(array $message): void
    {
        $messageId = $message['internetMessageId'] ?? $message['id'];
        
        // Check if email already exists
        $existingEmail = $this->emailRepository->findOneByField('message_id', $messageId);
        if ($existingEmail) {
            return;
        }

        // Check for reply relationships
        $parentEmail = $this->findParentEmail($message);

        // Map folder to supported folder enum
        $folderName = SupportedFolderEnum::INBOX->value;

        // Update parent email if found
        if ($parentEmail) {
            $this->emailRepository->update([
                'folders' => array_unique(array_merge($parentEmail->folders, [$folderName])),
                'reference_ids' => array_merge($parentEmail->reference_ids ?? [], [$messageId]),
            ], $parentEmail->id);
        }

        // Extract email data
        $from = $message['from']['emailAddress'] ?? [];
        $toRecipients = $message['toRecipients'] ?? [];
        $ccRecipients = $message['ccRecipients'] ?? [];
        $bccRecipients = $message['bccRecipients'] ?? [];
        $replyTo = $message['replyTo'] ?? [];

        // Get message body
        $body = $this->extractMessageBody($message);

        // Create email record
        $emailData = [
            'from' => $from['address'] ?? '',
            'subject' => $message['subject'] ?? '',
            'name' => $from['name'] ?? '',
            'reply' => $body,
            'is_read' => (int) ($message['isRead'] ?? false),
            'folders' => [$folderName],
            'reply_to' => $this->extractEmailAddresses($replyTo),
            'cc' => $this->extractEmailAddresses($ccRecipients),
            'bcc' => $this->extractEmailAddresses($bccRecipients),
            'source' => 'email',
            'user_type' => 'person',
            'unique_id' => $messageId,
            'message_id' => $messageId,
            'reference_ids' => [$messageId],
            'created_at' => $this->parseDateTime($message['receivedDateTime'] ?? now()),
            'parent_id' => $parentEmail?->id,
            'activity_id' => $parentEmail?->activity_id,
            'lead_id' => $parentEmail?->lead_id,
            'person_id' => $parentEmail?->person_id,
        ];

        // Link to existing entities based on email address
        $this->linkToExistingEntities($emailData, $from['address'] ?? '');

        $email = $this->emailRepository->create($emailData);

        Log::info('Processed Graph email', [
            'message_id' => $messageId,
            'email_id' => $email->id,
            'parent_id' => $parentEmail?->id
        ]);

        // Process attachments if any
        if ($message['hasAttachments'] ?? false) {
            $this->processAttachments($email, $message['id']);
        }

        // Mark message as read
        $this->markMessageAsRead($message['id']);
    }

    /**
     * Find parent email for reply relationships
     */
    protected function findParentEmail(array $message): ?Email
    {
        $messageId = $message['internetMessageId'] ?? $message['id'];
        $conversationId = $message['conversationId'] ?? null;

        // Check by conversation ID first
        if ($conversationId) {
            $parentEmail = $this->emailRepository->findOneWhere([
                ['reference_ids', 'like', '%' . $conversationId . '%']
            ]);
            if ($parentEmail) {
                return $parentEmail;
            }
        }

        // Check by message ID in references
        $toRecipients = $message['toRecipients'] ?? [];
        foreach ($toRecipients as $recipient) {
            $emailAddress = $recipient['emailAddress']['address'] ?? '';
            if ($email = $this->emailRepository->findOneWhere(['message_id' => $emailAddress])) {
                return $email;
            }
        }

        return null;
    }

    /**
     * Extract message body from Graph response
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
     * Extract email addresses from recipients array
     */
    protected function extractEmailAddresses(array $recipients): array
    {
        return collect($recipients)
            ->map(fn($recipient) => $recipient['emailAddress']['address'] ?? '')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Parse DateTime from Graph response
     */
    protected function parseDateTime(string $dateTime): Carbon
    {
        return Carbon::parse($dateTime)->setTimezone(config('app.timezone', 'UTC'));
    }

    /**
     * Link email to existing entities based on email address
     */
    protected function linkToExistingEntities(array &$emailData, string $emailAddress): void
    {
        if (empty($emailAddress)) {
            return;
        }

        // Try to find existing person by email
        $person = \Webkul\Contact\Models\Person::where('emails', 'like', '%' . $emailAddress . '%')->first();
        if ($person) {
            $emailData['person_id'] = $person->id;
            
            // Try to find associated lead through lead_persons table
            $lead = $person->leads()->first();
            if ($lead) {
                $emailData['lead_id'] = $lead->id;
            }
        }

        // Try to find existing lead by email if no person found
        if (!isset($emailData['lead_id'])) {
            $lead = \Webkul\Lead\Models\Lead::where('emails', 'like', '%' . $emailAddress . '%')->first();
            if ($lead) {
                $emailData['lead_id'] = $lead->id;
            }
        }

        // Create activity if we found a lead or person
        if (isset($emailData['lead_id']) || isset($emailData['person_id'])) {
            $activity = $this->createEmailActivity($emailData);
            if ($activity) {
                $emailData['activity_id'] = $activity->id;
            }
        }
    }

    /**
     * Create activity for the email
     */
    protected function createEmailActivity(array $emailData): ?\Webkul\Activity\Models\Activity
    {
        try {
            $activityData = [
                'title' => 'E-mail ontvangen: ' . ($emailData['subject'] ?: 'Geen onderwerp'),
                'comment' => 'E-mail ontvangen via Microsoft Graph',
                'type' => 'email',
                'schedule_from' => $emailData['created_at'] ?? now(),
                'schedule_to' => $emailData['created_at'] ?? now(),
                'is_completed' => true,
                'completed_at' => $emailData['created_at'] ?? now(),
            ];

            if (isset($emailData['lead_id'])) {
                $activityData['lead_id'] = $emailData['lead_id'];
            }

            if (isset($emailData['person_id'])) {
                $activityData['person_id'] = $emailData['person_id'];
            }

            return \Webkul\Activity\Models\Activity::create($activityData);
        } catch (Exception $e) {
            Log::error('Failed to create email activity', [
                'email_data' => $emailData,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Process attachments for a message
     */
    protected function processAttachments(Email $email, string $messageId): void
    {
        try {
            $accessToken = $this->getAccessToken();
            $mailbox = $this->mailbox;
            
            $url = "{$this->baseUrl}/users/{$mailbox}/messages/{$messageId}/attachments";
            
            $response = Http::withToken($accessToken)->get($url);
            
            if ($response->successful()) {
                $attachments = $response->json()['value'] ?? [];
                
                foreach ($attachments as $attachment) {
                    $this->attachmentRepository->create([
                        'email_id' => $email->id,
                        'name' => $attachment['name'] ?? 'attachment',
                        'content_type' => $attachment['contentType'] ?? 'application/octet-stream',
                        'size' => $attachment['size'] ?? 0,
                        'content' => base64_decode($attachment['contentBytes'] ?? ''),
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to process attachments', [
                'email_id' => $email->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mark message as read
     */
    protected function markMessageAsRead(string $messageId): void
    {
        try {
            $accessToken = $this->getAccessToken();
            $mailbox = $this->mailbox;
            
            $url = "{$this->baseUrl}/users/{$mailbox}/messages/{$messageId}";
            
            Http::withToken($accessToken)
                ->patch($url, [
                    'isRead' => true
                ]);
        } catch (Exception $e) {
            Log::error('Failed to mark message as read', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log sync start
     */
    protected function logSyncStart(): void
    {
        $this->currentLog = EmailLog::create([
            'sync_type' => 'graph',
            'started_at' => now(),
            'metadata' => [
                'mailbox' => $this->mailbox,
            ]
        ]);

        Log::info('Starting Microsoft Graph email sync', [
            'mailbox' => $this->mailbox,
            'log_id' => $this->currentLog->id,
            'timestamp' => now()
        ]);
    }

    /**
     * Log sync completion
     */
    protected function logSyncComplete(int $processedCount, int $errorCount): void
    {
        if ($this->currentLog) {
            $this->currentLog->update([
                'completed_at' => now(),
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
            ]);
        }

        Log::info('Microsoft Graph email sync completed', [
            'processed_count' => $processedCount,
            'error_count' => $errorCount,
            'log_id' => $this->currentLog?->id,
            'timestamp' => now()
        ]);
    }

    /**
     * Log sync error
     */
    protected function logSyncError(string $error): void
    {
        if ($this->currentLog) {
            $this->currentLog->update([
                'completed_at' => now(),
                'error_message' => $error,
            ]);
        }

        Log::error('Microsoft Graph email sync failed', [
            'error' => $error,
            'log_id' => $this->currentLog?->id,
            'timestamp' => now()
        ]);
    }
}