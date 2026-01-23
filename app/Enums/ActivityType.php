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
    case EMAIL = 'email';
    case PATIENT_MESSAGE = 'patient_message';

    /**
     * Get all user-selectable activity types (excludes SYSTEM)
     */
    public static function userSelectable(): array
    {
        return array_filter(self::cases(), fn ($case) => ! in_array($case, [self::SYSTEM, self::FILE, self::EMAIL], true));
    }

    public function label(): string
    {
        return match ($this) {
            self::CALL            => trans('admin::app.components.activities.index.calls'),
            self::MEETING         => trans('admin::app.components.activities.index.meetings'),
            self::TASK            => trans('admin::app.components.activities.index.internal-task'),
            self::SYSTEM          => trans('admin::app.components.activities.index.change-log'),
            self::NOTE            => trans('admin::app.components.activities.index.notes'),
            self::FILE            => trans('admin::app.components.activities.index.files'),
            self::EMAIL           => trans('admin::app.components.activities.index.emails'),
            self::PATIENT_MESSAGE => 'Patient Messages',
        };
    }

    /**
     * Check if this activity type is selectable by users
     */
    public function isUserSelectable(): bool
    {
        return ! in_array($this, [self::SYSTEM, self::NOTE, self::FILE, self::EMAIL, self::PATIENT_MESSAGE], true);
    }

    public static function canBeMarkedAsDone(): array {
        return array_values(array_filter(
            self::cases(),
            fn (self $stage) => ! in_array($stage, [
                    self::MEETING,
                    self::NOTE,
                ], true)
        ));
    }
}
