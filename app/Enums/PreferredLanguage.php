<?php

namespace App\Enums;

enum PreferredLanguage: string
{
    case NL = 'nl';
    case EN = 'en';
    case DE = 'de';

    public function label(): string
    {
        return match ($this) {
            self::NL => 'Nederlands',
            self::EN => 'Engels',
            self::DE => 'Duits',
        };
    }
}
