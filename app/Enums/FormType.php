<?php

namespace App\Enums;

enum FormType: string
{
    case PrivateScan = 'privatescan';
    /** Diagnose form from website webhook by lead */
    case HerniaDiagnosisForm = 'herniapoli';
    case HerniaNarcoseForm = 'hernianarcose';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromValue(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::PrivateScan;
    }

    public function label(): string
    {
        return match ($this) {
            self::PrivateScan         => 'GVL',
            self::HerniaDiagnosisForm => 'Herniapoli',
            self::HerniaNarcoseForm   => 'Narcose',
        };
    }
}
