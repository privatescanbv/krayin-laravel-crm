<?php

namespace App\Enums;

enum ActivityType: string
{
    case CALL = 'call';
    case MEETING = 'meeting';
    case TASK = 'task';
    case SYSTEM = 'system';

    /**
     * Get all user-selectable activity types (excludes SYSTEM)
     */
    public static function userSelectable(): array
    {
        return array_filter(self::cases(), fn ($case) => $case !== self::SYSTEM);
    }

    /**
     * Check if this activity type is selectable by users
     */
    public function isUserSelectable(): bool
    {
        return $this !== self::SYSTEM;
    }
}
