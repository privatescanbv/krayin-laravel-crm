<?php

namespace App\Enums;

enum PathDivider: string
{
    case SLASH = '/';

    public static function default(): self
    {
        return self::SLASH;
    }

    public static function value(): string
    {
        return self::default()->value;
    }
}
