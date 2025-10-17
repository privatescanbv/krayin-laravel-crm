<?php

namespace App\Enums;

enum ReportingType: string
{
    case FINANCIAL = 'financial';
    case OPERATIONAL = 'operational';
    case CLINICAL = 'clinical';
    case MARKETING = 'marketing';
    case COMPLIANCE = 'compliance';
    case QUALITY = 'quality';

    public function getLabel(): string
    {
        return match($this) {
            self::FINANCIAL => 'Financial',
            self::OPERATIONAL => 'Operational',
            self::CLINICAL => 'Clinical',
            self::MARKETING => 'Marketing',
            self::COMPLIANCE => 'Compliance',
            self::QUALITY => 'Quality',
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())->map(function ($case) {
            return [
                'value' => $case->value,
                'label' => $case->getLabel(),
            ];
        })->toArray();
    }

    public static function getLabels(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->getLabel()];
        })->toArray();
    }
}