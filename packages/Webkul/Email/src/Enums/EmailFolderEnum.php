<?php

namespace Webkul\Email\Enums;

enum EmailFolderEnum: string
{
    case INBOX = 'Inbox';
    case IMPORTED = 'Imported';
    case SENT = 'Sent';
    case DRAFT = 'Draft';
    case TRASH = 'Trash';
    case IMPORTANT = 'Important';
    case ARCHIVE = 'Archive';

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
