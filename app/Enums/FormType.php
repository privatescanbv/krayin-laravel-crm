<?php

namespace App\Enums;

use App\Models\Anamnesis;
use App\Models\Order;
use App\Services\Anamnesis\AnamnesisOrderResolver;

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

    /** Cases available for manual selection (excludes webhook-only types). */
    public static function manualCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $t) => $t !== self::HerniaDiagnosisForm));
    }

    public static function defaultForAnamnesis(Anamnesis $anamnesis): self
    {
        $department = app(AnamnesisOrderResolver::class)->resolveFormDepartment($anamnesis);

        return ($department && $department->isHernia()) ? self::HerniaNarcoseForm : self::PrivateScan;
    }

    public static function defaultForOrder(Order $order): self
    {
        return $order->isHerniapoli() ? self::HerniaNarcoseForm : self::PrivateScan;
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
