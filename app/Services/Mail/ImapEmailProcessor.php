<?php

namespace App\Services\Mail;

use Carbon\Carbon;
use Exception;
use Schema;
use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Message;
use Webkul\Email\Enums\SupportedFolderEnum;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Email\Models\Email;

class ImapEmailProcessor extends AbstractEmailProcessor
{
    protected $client;

    public function __construct(
        EmailRepository $emailRepository,
        AttachmentRepository $attachmentRepository
    ) {
        parent::__construct($emailRepository, $attachmentRepository);
        
        // Skip IMAP connection during testing or when database is not available
        if (!$this->isDatabaseAvailable()) {
            logger()->warning('Skipping IMAP when database is not available.');
            return;
        }
        try {
            $this->reconnect();
        } catch (Exception $e) {
            logger()->error('Reconnect fail for email processing: ' . $e->getMessage());
        }
    }

    /**
     * Close the connection.
     */
    public function __destruct()
    {
        if ($this->client) {
            $this->client->disconnect();
        }
    }

    /**
     * @throws Exception, with email client connection errors
     */
    private function reconnect(): void
    {
        if (!$this->client) {
            logger()->info('Reconnecting: Establishing IMAP connection...', ['config' => $this->getDefaultConfigs()]);
            $this->client = Client::make($this->getDefaultConfigs());

            $this->client->connect();

            if (!$this->client->isConnected()) {
                // reset client for next attempt
                $this->client = null;
                logger()->error('Failed to connect to the mail server.');
                throw new Exception('Failed to connect to the mail server.');
            }
        }
    }

    // Abstract method implementations

    protected function fetchMessages(): array
    {
        $this->reconnect();
        if (!$this->client) {
            logger()->warning('IMAP client is not initialized. Skipping email processing.');
            return [];
        }

        try {
            $rootFolders = $this->client->getFolders();
            $messages = [];
            
            $this->collectMessagesFromFolders($rootFolders, $messages);
            
            return $messages;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    protected function isValidMessage($message): bool
    {
        return $message instanceof Message;
    }

    protected function getMessageId($message): string
    {
        $attributes = $message->getAttributes();
        return $attributes['message_id']->first();
    }

    protected function getConversationId($message): ?string
    {
        $attributes = $message->getAttributes();
        return $attributes['in_reply_to']->first() ?? null;
    }

    protected function getFolderName($message): string
    {
        $folderName = match ($message->getFolder()->name) {
            'INBOX'     => SupportedFolderEnum::INBOX->value,
            'Important' => SupportedFolderEnum::IMPORTANT->value,
            'Starred'   => SupportedFolderEnum::STARRED->value,
            'Drafts'    => SupportedFolderEnum::DRAFT->value,
            'Sent Mail' => SupportedFolderEnum::SENT->value,
            'Trash'     => SupportedFolderEnum::TRASH->value,
            default     => '',
        };

        return $folderName;
    }

    protected function extractEmailData($message, string $folderName, ?Email $parentEmail): array
    {
        $attributes = $message->getAttributes();
        $messageId = $this->getMessageId($message);

        $references = [$messageId];
        if (isset($attributes['references'])) {
            array_push($references, ...$attributes['references']->all());
        }

        return [
            'from'          => $attributes['from']->first()->mail,
            'subject'       => $attributes['subject']->first(),
            'name'          => $attributes['from']->first()->personal,
            'reply'         => $message->bodies['html'] ?? $message->bodies['text'],
            'is_read'       => (int) $message->flags()->has('seen'),
            'folders'       => [$folderName],
            'reply_to'      => $this->getEmailsByAttributeCode($attributes, 'to'),
            'cc'            => $this->getEmailsByAttributeCode($attributes, 'cc'),
            'bcc'           => $this->getEmailsByAttributeCode($attributes, 'bcc'),
            'source'        => 'email',
            'user_type'     => 'person',
            'unique_id'     => $messageId,
            'message_id'    => $messageId,
            'reference_ids' => $references,
            'created_at'    => $this->convertToDesiredTimezone($message->date->toDate()),
            'parent_id'     => $parentEmail?->id,
            'activity_id'   => $parentEmail?->activity_id,
            'lead_id'       => $parentEmail?->lead_id,
            'person_id'     => $parentEmail?->person_id,
        ];
    }

    protected function getFromEmail($message): string
    {
        $attributes = $message->getAttributes();
        return $attributes['from']->first()->mail;
    }

    protected function getToRecipients($message): array
    {
        $attributes = $message->getAttributes();
        return $this->getEmailsByAttributeCode($attributes, 'to');
    }

    protected function extractEmailFromRecipient($recipient): ?string
    {
        return is_string($recipient) ? $recipient : null;
    }

    protected function hasAttachments($message): bool
    {
        return $message->hasAttachments();
    }

    protected function processAttachments(Email $email, $message): void
    {
        $this->attachmentRepository->uploadAttachments($email, [
            'source'      => 'email',
            'attachments' => $message->getAttachments(),
        ]);
    }

    protected function markMessageAsRead($message): void
    {
        // IMAP messages are automatically marked as read when fetched
        // This is handled by the IMAP library configuration
    }

    protected function getSyncType(): string
    {
        return 'imap';
    }

    protected function getProcessorName(): string
    {
        return 'IMAP';
    }

    protected function getSyncMetadata(): array
    {
        return [
            'host' => config('imap.accounts.default.host'),
            'username' => config('imap.accounts.default.username'),
        ];
    }

    // Helper methods specific to IMAP

    /**
     * Collect messages from all folders
     */
    protected function collectMessagesFromFolders($rootFoldersCollection, &$messages): void
    {
        $rootFoldersCollection->each(function ($folder) use (&$messages) {
            if (!$folder->children->isEmpty()) {
                $this->collectMessagesFromFolders($folder->children, $messages);
                return;
            }

            if (in_array($folder->name, ['All Mail'])) {
                return;
            }

            $folderMessages = $folder->query()->since(now()->subDays(10))->get();
            foreach ($folderMessages as $message) {
                $messages[] = $message;
            }
        });
    }

    /**
     * Get the emails by the attribute code.
     */
    protected function getEmailsByAttributeCode(array $attributes, string $attributeCode): array
    {
        $emails = [];

        if (isset($attributes[$attributeCode])) {
            $emails = collect($attributes[$attributeCode]->all())->map(fn ($attribute) => $attribute->mail)->toArray();
        }

        return $emails;
    }

    /**
     * Convert the date to the desired timezone.
     */
    protected function convertToDesiredTimezone($carbonDate, $targetTimezone = null)
    {
        $targetTimezone = $targetTimezone ?: config('app.timezone');

        return $carbonDate->clone()->setTimezone($targetTimezone);
    }

    /**
     * Get the default configurations.
     */
    protected function getDefaultConfigs(): array
    {
        $defaultConfig = config('imap.accounts.default');

        $defaultConfig['host'] = core()->getConfigData('email.imap.account.host') ?: $defaultConfig['host'];
        $defaultConfig['port'] = core()->getConfigData('email.imap.account.port') ?: $defaultConfig['port'];
        $defaultConfig['encryption'] = core()->getConfigData('email.imap.account.encryption') ?: $defaultConfig['encryption'];
        $defaultConfig['validate_cert'] = (bool) core()->getConfigData('email.imap.account.validate_cert');
        $defaultConfig['username'] = core()->getConfigData('email.imap.account.username') ?: $defaultConfig['username'];
        $defaultConfig['password'] = core()->getConfigData('email.imap.account.password') ?: $defaultConfig['password'];

        return $defaultConfig;
    }

    /**
     * Check if the database is available and has the required tables.
     */
    protected function isDatabaseAvailable(): bool
    {
        try {
            // Check if the core_config table exists
            return Schema::hasTable('core_config');
        } catch (Exception $e) {
            logger()->error('Database is not available: ' . $e->getMessage());
            return false;
        }
    }
}