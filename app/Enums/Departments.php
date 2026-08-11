<?php

namespace App\Enums;

use ValueError;

/**
 *  Also define group in user and for filtering in activities
 */
enum Departments: string
{
    case HERNIA = 'Herniapoli';
    case PRIVATESCAN = 'Privatescan';

    /**
     * @throws ValueError als de sleutel bij geen enkele case hoort
     */
    public static function fromKey(string $key): self
    {
        return self::tryFromKey($key) ?? throw new ValueError("Unknown department key: $key");
    }

    /**
     * Inverse van key(). Afgeleid van key() zelf, zodat de twee niet uit elkaar kunnen lopen.
     */
    public static function tryFromKey(string $key): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->key() === $key) {
                return $case;
            }
        }

        return null;
    }

    public static function allValues(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Lowercase workflow/view key ('herniapoli' | 'privatescan').
     *
     * Expliciet gemapt in plaats van afgeleid van $value: deze sleutel is een extern
     * contract (querystrings, activity-view keys, mailbox_key op Email-records), dus
     * hernoemen van een label mag hem niet stilzwijgend veranderen.
     *
     * Let op: de losse string 'hernia' die o.a. de clinic guide en de order-editor
     * gebruiken is een eigen, ongerelateerde sleutelruimte — niet deze.
     */
    public function key(): string
    {
        return match ($this) {
            self::HERNIA      => 'herniapoli',
            self::PRIVATESCAN => 'privatescan',
        };
    }
}
