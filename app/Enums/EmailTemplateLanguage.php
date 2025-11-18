<?php

namespace App\Enums;

enum EmailTemplateLanguage: string
{
    case NEDERLANDS = 'nl';
    case DUITS = 'de';
    case ENGELS = 'en';

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
            self::NEDERLANDS => 'Nederlands',
            self::DUITS      => 'Duits',
            self::ENGELS     => 'Engels',
        };
    }
}
