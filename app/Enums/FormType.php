<?php

namespace App\Enums;

enum FormType: string
{
    case PrivateScan = 'privatescan';
    case HerniaPoli = 'herniapoli';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromValue(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::PrivateScan;
    }

}
