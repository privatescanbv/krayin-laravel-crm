<?php

namespace App\Enums;

enum ActivityType: string
{
    case CALL = 'call';
    //    case MEETING = 'meeting';
    case TASK = 'task';
    case SYSTEM = 'system';
    case NOTE = 'note';
    case FILE = 'file';
    case PATIENT_MESSAGE = 'patient_message';
    // clinic portaal
    case TEAM_CHAT = 'team_chat';

    /**
     * Get all user-selectable activity types (excludes SYSTEM)
     */
    public static function userSelectable(): array
    {
        return array_filter(self::cases(), fn ($case) => ! in_array($case, [self::SYSTEM, self::FILE, self::PATIENT_MESSAGE, self::TEAM_CHAT], true));
    }

    public static function canBeMarkedAsDone(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $stage) => ! in_array($stage, [
                self::NOTE,
                self::SYSTEM,
                self::TEAM_CHAT,
            ], true)
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::CALL            => trans('admin::app.components.activities.index.calls'),
            self::TASK            => trans('admin::app.components.activities.index.internal-task'),
            self::SYSTEM          => trans('admin::app.components.activities.index.change-log'),
            self::NOTE            => trans('admin::app.components.activities.index.notes'),
            self::FILE            => trans('admin::app.components.activities.index.files'),
            self::PATIENT_MESSAGE => 'Patient Messages',
            self::TEAM_CHAT       => 'Teamchat',
        };
    }

    public function hasDeadline(): bool
    {
        return ! in_array($this, [self::NOTE, self::FILE, self::SYSTEM, self::PATIENT_MESSAGE, self::TEAM_CHAT], true);
    }

    /**
     * Check if this activity type is selectable by users
     */
    public function isUserSelectable(): bool
    {
        return ! in_array($this, [self::SYSTEM, self::NOTE, self::FILE, self::PATIENT_MESSAGE, self::TEAM_CHAT], true);
    }
}
