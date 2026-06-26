<?php

namespace Webkul\Email\Enums;

enum EmailFolderEnum: string
{
    case INBOX = 'Inbox Privatescan';
    case INBOX_HERNIAPOLI = 'Inbox Herniapoli';
    case PROCESSED = 'Verwerkt';
    case SENT_PRIVATESCAN = 'Sent Privatescan';
    case SENT_HERNIAPOLI = 'Sent HerniaPoli';
    case DRAFT = 'Draft';
    case TRASH = 'Trash';
    case NO_FOLLOW_UP = 'Geen opvolging';

    /**
     * Get the folder name for the enum case
     */
    public function getFolderName(): string
    {
        return $this->value;
    }

    /**
     * Get all folder names as array
     */
    public static function getAllFolderNames(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get folder enum by name
     */
    public static function fromFolderName(string $folderName): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $folderName) {
                return $case;
            }
        }
        return null;
    }

    public static function sentFolderNameForMailbox(?string $mailboxKey = null): string
    {
        $mailboxes = config('mail.mailboxes', []);

        if ($mailboxKey && isset($mailboxes[$mailboxKey]['sent_folder_name'])) {
            return $mailboxes[$mailboxKey]['sent_folder_name'];
        }

        if ($mailboxKey) {
            return match ($mailboxKey) {
                'herniapoli' => self::SENT_HERNIAPOLI->value,
                default => self::SENT_PRIVATESCAN->value,
            };
        }

        $defaultKey = array_key_first($mailboxes);

        if ($defaultKey && isset($mailboxes[$defaultKey]['sent_folder_name'])) {
            return $mailboxes[$defaultKey]['sent_folder_name'];
        }

        return self::SENT_PRIVATESCAN->value;
    }
}
