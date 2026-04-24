<?php

namespace App\Services\Mail;

use App\Models\Clinic;
use App\Models\EmailLog;
use App\Models\SalesLead;
use Exception;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Lead\Models\Lead;

/**
 * Shared orchestration logic for all inbound email processors.
 *
 * Handles the parts of message processing that are protocol-agnostic:
 *  - Deduplication: skips messages whose message_id or unique_id already exist in the database.
 *  - Thread detection: finds a parent Email record via conversation ID or To-recipient matching.
 *  - Entity linking: matches the sender's email address against Person, Lead, SalesLead, and Clinic
 *    records and populates the corresponding foreign keys on the new Email record.
 *  - Sync logging: creates and updates an EmailLog entry to track each sync run's outcome.
 *
 * Concrete subclasses implement the protocol-specific primitives declared as abstract methods
 * (fetching, message-ID extraction, folder mapping, attachment handling, etc.).
 *
 * @see GraphMailService  Microsoft Graph (Exchange Online) implementation
 * @see ImapEmailProcessor IMAP implementation
 */
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
        $emailData = $this->linkToExistingEntities($emailData, $this->getFromEmail($message));

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
        //        $messageId = $this->getMessageId($message);
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
     * Link email to existing entities based on sender email address.
     *
     * Priority (first match wins per entity type, all types are checked):
     *  1. Person   – match sender against persons.emails JSON (value field).
     *  2. SalesLead – newest active (stage not is_won/is_lost) sales lead linked to the matched person.
     *  3. Lead      – newest active (stage not is_won/is_lost) lead linked to the matched person,
     *                 or by lead.emails JSON if no person was found.
     *  4. Clinic    – match sender domain against clinic.emails JSON (plain string array).
     *
     * Note: reply/parent relation copying is handled separately in EmailRepository::create().
     */
    protected function linkToExistingEntities(array $emailData, string $emailAddress): array
    {
        if (empty($emailAddress)) {
            return $emailData;
        }

        // 1. Find person by email address
        $person = Person::where('emails', 'like', '%'.$emailAddress.'%')->first();

        if ($person) {
            $emailData['person_id'] = $person->id;

            // 2. Find newest active sales lead via person
            $salesLead = SalesLead::whereHas('persons', fn ($q) => $q->where('persons.id', $person->id))
                ->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))
                ->latest()
                ->first();

            if ($salesLead) {
                $emailData['sales_lead_id'] = $salesLead->id;
            }

            // 3. Find newest active lead via person
            $lead = Lead::whereHas('persons', fn ($q) => $q->where('persons.id', $person->id))
                ->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))
                ->latest()
                ->first();

            if ($lead) {
                $emailData['lead_id'] = $lead->id;
            }
        } else {
            // 3b. No person found – try to match lead directly by emails JSON
            $lead = Lead::where('emails', 'like', '%'.$emailAddress.'%')
                ->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))
                ->latest()
                ->first();

            if ($lead) {
                $emailData['lead_id'] = $lead->id;
            }
        }

        // 4. Find clinic by sender email address
        $clinic = Clinic::where('emails', 'like', '%'.$emailAddress.'%')->first();

        if ($clinic) {
            $emailData['clinic_id'] = $clinic->id;
        }

        return $emailData;
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
