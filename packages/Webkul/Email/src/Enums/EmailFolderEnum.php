<?php

namespace Webkul\Email\Enums;

enum EmailFolderEnum: string
{
    case INBOX = 'Inbox';
    case PRIVATESCAN_WEBFORM = 'Privatescan webforms';
    case HERNIA_WEBFORM = 'Hernia Poli webforms';
    case CLINICS = 'Klinieken';
    case NEWSLETTER = 'Nieuwsbrief reacties';
    case PROCESSED = 'Verwerkt';
    case SENT = 'Sent';
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
}
