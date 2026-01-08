<?php

namespace App\Services\Mail;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\EmailLog;
use Exception;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Lead\Models\Lead;

abstract class AbstractEmailProcessor implements InboundEmailProcessor
{
    protected ?EmailLog $currentLog = null;

    public function __construct(
        protected EmailRepository $emailRepository,
        protected AttachmentRepository $attachmentRepository
    ) {
        // Constructor logic can be added here if needed
    }

    /**
     * Process messages from all folders.
     */
    public function processMessagesFromAllFolders(): void
    {
        try {
            $this->logSyncStart();

            $messages = $this->fetchMessages();
            $processedCount = 0;
            $errorCount = 0;

            foreach ($messages as $message) {
                try {
                    $this->processMessage($message);
                    $processedCount++;
                } catch (Exception $e) {
                    $errorCount++;
                    Log::error('Failed to process message', [
                        'message_id' => $this->getMessageId($message),
                        'error'      => $e->getMessage(),
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
     * Process a single message
     */
    public function processMessage($message = null): void
    {
        if (! $message || ! $this->isValidMessage($message)) {
            return;
        }

        $messageId = $this->getMessageId($message);

        // Check if email already exists by message_id
        $existingEmail = $this->emailRepository->findOneByField('message_id', $messageId);
        if ($existingEmail) {
            Log::warning('Email already exists, skipping', [
                'message_id' => $messageId,
                'email_id'   => $existingEmail->id,
                'processor'  => static::class,
            ]);

            return;
        }

        // Check if email already exists by unique_id (do this before extractEmailData to avoid errors with missing from field)
        $existingEmailByUniqueId = $this->emailRepository->findOneByField('unique_id', $messageId);
        if ($existingEmailByUniqueId) {
            Log::warning('Email with same unique_id already exists, skipping', [
                'message_id'        => $messageId,
                'unique_id'         => $messageId,
                'existing_email_id' => $existingEmailByUniqueId->id,
                'processor'         => static::class,
            ]);

            return;
        }

        // Check for reply relationships
        $parentEmail = $this->findParentEmail($message);

        // Map folder to supported folder enum
        $folderName = $this->getFolderName($message);

        // Update parent email if found
        if ($parentEmail) {
            $this->emailRepository->update([
                'folder_id'     => $this->getFolderId($folderName),
                'reference_ids' => array_merge($parentEmail->reference_ids ?? [], [$messageId]),
            ], $parentEmail->id);
        }

        // Extract email data
        $emailData = $this->extractEmailData($message, $folderName, $parentEmail);

        // Link to existing entities based on email address
        $this->linkToExistingEntities($emailData, $this->getFromEmail($message));

        $email = $this->emailRepository->create($emailData);

        Log::info('Processed email', [
            'message_id' => $messageId,
            'email_id'   => $email->id,
            'parent_id'  => $parentEmail?->id,
            'processor'  => static::class,
        ]);

        // Process attachments if any
        if ($this->hasAttachments($message)) {
            $this->processAttachments($email, $message);
        }

        // Mark message as read
        $this->markMessageAsRead($message);
    }

    /**
     * Find parent email for reply relationships
     */
    protected function findParentEmail($message): ?Email
    {
        $messageId = $this->getMessageId($message);
        $conversationId = $this->getConversationId($message);

        // Check by conversation ID first
        if ($conversationId) {
            $parentEmail = $this->emailRepository->findOneWhere([
                ['reference_ids', 'like', '%'.$conversationId.'%'],
            ]);
            if ($parentEmail) {
                return $parentEmail;
            }
        }

        // Check by message ID in references
        $toRecipients = $this->getToRecipients($message);
        foreach ($toRecipients as $recipient) {
            $emailAddress = $this->extractEmailFromRecipient($recipient);
            if ($emailAddress && $email = $this->emailRepository->findOneWhere(['message_id' => $emailAddress])) {
                return $email;
            }
        }

        return null;
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
        $person = Person::where('emails', 'like', '%'.$emailAddress.'%')->first();
        if ($person) {
            $emailData['person_id'] = $person->id;

            // Try to find associated lead through lead_persons table
            $lead = $person->leads->first();
            if ($lead) {
                $emailData['lead_id'] = $lead->id;
            }
        }

        // Try to find existing lead by email if no person found
        if (! isset($emailData['lead_id'])) {
            $lead = Lead::where('emails', 'like', '%'.$emailAddress.'%')->first();
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
    protected function createEmailActivity(array $emailData): ?Activity
    {
        try {
            $activityData = [
                'title'         => 'E-mail ontvangen: '.($emailData['subject'] ?: 'Geen onderwerp'),
                'comment'       => 'E-mail ontvangen via '.$this->getProcessorName(),
                'type'          => ActivityType::EMAIL,
                'schedule_from' => $emailData['created_at'] ?? now(),
                'schedule_to'   => $emailData['created_at'] ?? now(),
                'is_done'       => true,
                'status'        => ActivityStatus::DONE,
            ];

            if (isset($emailData['lead_id'])) {
                $activityData['lead_id'] = $emailData['lead_id'];
            }

            if (isset($emailData['person_id'])) {
                $activityData['person_id'] = $emailData['person_id'];
            }

            return Activity::create($activityData);
        } catch (Exception $e) {
            Log::error('Failed to create email activity', [
                'email_data' => $emailData,
                'error'      => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Log sync start
     */
    protected function logSyncStart(): void
    {
        $this->currentLog = EmailLog::create([
            'sync_type'  => $this->getSyncType(),
            'started_at' => now(),
            'metadata'   => $this->getSyncMetadata(),
        ]);
    }

    /**
     * Log sync completion
     */
    protected function logSyncComplete(int $processedCount, int $errorCount): void
    {
        if ($this->currentLog) {
            $this->currentLog->update([
                'completed_at'    => now(),
                'processed_count' => $processedCount,
                'error_count'     => $errorCount,
            ]);
        }

        if ($processedCount > 1) {
            Log::info('Email sync completed', [
                'processor'       => static::class,
                'processed_count' => $processedCount,
                'error_count'     => $errorCount,
                'log_id'          => $this->currentLog?->id,
                'timestamp'       => now(),
            ]);
        }
    }

    /**
     * Log sync error
     */
    protected function logSyncError(string $error): void
    {
        if ($this->currentLog) {
            $this->currentLog->update([
                'completed_at'  => now(),
                'error_message' => $error,
            ]);
        }

        Log::error('Email sync failed', [
            'processor' => static::class,
            'error'     => $error,
            'log_id'    => $this->currentLog?->id,
            'timestamp' => now(),
        ]);
    }

    // Abstract methods that must be implemented by concrete processors

    /**
     * Fetch messages from the email source
     */
    abstract protected function fetchMessages(): array;

    /**
     * Check if the message is valid
     */
    abstract protected function isValidMessage($message): bool;

    /**
     * Get message ID from the message
     */
    abstract protected function getMessageId($message): string;

    /**
     * Get conversation ID from the message
     */
    abstract protected function getConversationId($message): ?string;

    /**
     * Get folder name from the message
     */
    abstract protected function getFolderName($message): string;

    /**
     * Extract email data from the message
     */
    abstract protected function extractEmailData($message, string $folderName, ?Email $parentEmail): array;

    /**
     * Get from email address
     */
    abstract protected function getFromEmail($message): string;

    /**
     * Get to recipients
     */
    abstract protected function getToRecipients($message): array;

    /**
     * Extract email from recipient
     */
    abstract protected function extractEmailFromRecipient($recipient): ?string;

    /**
     * Check if message has attachments
     */
    abstract protected function hasAttachments($message): bool;

    /**
     * Process attachments for a message
     */
    abstract protected function processAttachments(Email $email, $message): void;

    /**
     * Mark message as read
     */
    abstract protected function markMessageAsRead($message): void;

    /**
     * Get sync type for logging
     */
    abstract protected function getSyncType(): string;

    /**
     * Get processor name for activity creation
     */
    abstract protected function getProcessorName(): string;

    /**
     * Get sync metadata for logging
     */
    abstract protected function getSyncMetadata(): array;

    /**
     * Get folder ID by name
     *
     * @param  string  $folderName
     * @return int|null
     */
    protected function getFolderId($folderName)
    {
        $folder = Folder::where('name', strtolower($folderName))->first();

        return $folder ? $folder->id : null;
    }
}
