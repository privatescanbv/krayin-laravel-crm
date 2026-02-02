<?php

namespace App\Enums;

enum EmailTemplateType: string
{
    case LEAD = 'lead';
    case ALGEMEEN = 'algemeen';
    case ORDER = 'order';
    case GVL = 'gvl';
    case PATIENT = 'patient';

    /**
     * Get all values as array
     */
    public static function allValues(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Get all cases as array with labels
     */
    public static function allWithLabels(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::LEAD         => 'Lead',
            self::ALGEMEEN     => 'Algemeen',
            self::ORDER        => 'Order',
            self::GVL          => 'GVL',
            self::PATIENT      => 'Patient',
        };
    }
}
