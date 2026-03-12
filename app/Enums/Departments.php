<?php

namespace App\Enums;

/**
 *  Also define group in user and for filtering in activities
 */
enum Departments: string
{
    case HERNIA = 'Herniapoli';
    case PRIVATESCAN = 'Privatescan';

    public static function allValues(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}
