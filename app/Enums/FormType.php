<?php

namespace App\Enums;

use App\Models\Anamnesis;
use App\Models\Order;
use App\Services\Anamnesis\AnamnesisOrderResolver;

enum FormType: string
{
    case PrivateScan = 'privatescan';
    /** Legacy: diagnose form from website webhook by lead. No longer produced; kept for old rows. */
    case HerniaDiagnosisForm = 'herniapoli';
    case HerniaNarcoseForm = 'hernianarcose';
    /** Herniapoli diagnose form, set up manually from a Sale (patient portal). */
    case HerniaBackPainForm = 'herniapoli_lagerugpijn';
    case HerniaNeckPainForm = 'herniapoli_nekpijn';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Cases available for manual selection in the GVL/Narcose form picker. */
    public static function manualCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $t) => $t->isGvlForm()));
    }

    /** Herniapoli diagnose form types a Herniapoli employee can set up from a Sale. */
    public static function diagnosisCases(): array
    {
        return [self::HerniaBackPainForm, self::HerniaNeckPainForm];
    }

    /** Values that belong to the GVL flow (GVL + Narcose) — for query filters. */
    public static function gvlValues(): array
    {
        return array_values(array_map(
            fn (self $t) => $t->value,
            array_filter(self::cases(), fn (self $t) => $t->isGvlForm())
        ));
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

    public function isDiagnosisForm(): bool
    {
        return in_array($this, [self::HerniaDiagnosisForm, self::HerniaBackPainForm, self::HerniaNeckPainForm], true);
    }

    /**
     * A "GVL flow" form: the health questionnaire (GVL) or the Narcose form. These are shown in
     * the anamnesis "GVL Formulieren" block and attached to the clinic AFB mail. Diagnose forms
     * are a separate concept with their own block.
     */
    public function isGvlForm(): bool
    {
        return in_array($this, [self::PrivateScan, self::HerniaNarcoseForm], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PrivateScan         => 'GVL',
            self::HerniaDiagnosisForm => 'Diagnoseformulier',
            self::HerniaNarcoseForm   => 'Narcose',
            self::HerniaBackPainForm  => 'Diagnose lage rugpijn',
            self::HerniaNeckPainForm  => 'Diagnose nekpijn',
        };
    }
}
