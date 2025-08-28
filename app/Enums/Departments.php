<?php

namespace App\Enums;

enum Departments: string
{
    case HERNIA = 'Herniapoli';
    case PRIVATESCAN = 'Privatescan';

    public static function allValues(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}
