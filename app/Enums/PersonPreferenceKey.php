<?php

namespace App\Enums;

enum PersonPreferenceKey: string
{
    case EMAIL_NOTIFICATIONS_ENABLED = 'email_notifications_enabled';

    /**
     * Get the default value for this preference key.
     */
    public function defaultValue(): mixed
    {
        return match ($this) {
            self::EMAIL_NOTIFICATIONS_ENABLED => true,
        };
    }

    /**
     * Get the value type for this preference key.
     */
    public function valueType(): string
    {
        return match ($this) {
            self::EMAIL_NOTIFICATIONS_ENABLED => 'bool',
        };
    }

    /**
     * Check if this preference is system managed (not editable by patient).
     */
    public function isSystemManaged(): bool
    {
        return match ($this) {
            self::EMAIL_NOTIFICATIONS_ENABLED => false,
        };
    }
}
