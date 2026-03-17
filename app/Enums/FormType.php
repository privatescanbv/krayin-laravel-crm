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

    public function layout(): string
    {
        return match ($this) {
            self::PrivateScan => 'layouts.app',
            self::HerniaPoli  => 'layouts.herniapoli',
        };
    }

    public function brandName(): string
    {
        return match ($this) {
            self::PrivateScan => 'PrivateScan',
            self::HerniaPoli  => 'Herniapoli',
        };
    }

    public function supportEmail(): string
    {
        return match ($this) {
            self::PrivateScan => 'info@privatescan.nl',
            self::HerniaPoli  => 'info@herniapoli.nl',
        };
    }
}
