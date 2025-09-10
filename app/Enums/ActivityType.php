<?php

namespace App\Enums;

enum ActivityType: string
{
    case CALL = 'call';
    case MEETING = 'meeting';
    case TASK = 'task';
    case SYSTEM = 'system';
    case NOTE = 'note';
    case FILE = 'file';

    /**
     * Get all user-selectable activity types (excludes SYSTEM)
     */
    public static function userSelectable(): array
    {
        return array_filter(self::cases(), fn ($case) => ! in_array($case, [self::SYSTEM, self::NOTE, self::FILE], true));
    }

    /**
     * Check if this activity type is selectable by users
     */
    public function isUserSelectable(): bool
    {
        return ! in_array($this, [self::SYSTEM, self::NOTE, self::FILE], true);
    }
}
