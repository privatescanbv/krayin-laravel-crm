<?php

namespace App\Enums;

enum PersonSalutation: string
{
    case Dhr = 'Dhr.';
    case Mevr = 'Mevr.';

    public function label(): string
    {
        return match ($this) {
            self::Dhr  => __('Dhr.'),
            self::Mevr => __('Mevr.'),
        };
    }

    public function labelDe(): string
    {
        return match ($this) {
            self::Dhr  => 'Herr',
            self::Mevr => 'Frau',
        };
    }
}
