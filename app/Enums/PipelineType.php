<?php

namespace App\Enums;

enum PipelineType: string
{
    case LEAD = 'lead';
    case WORKFLOW = 'workflow';

    /**
     * Get all enum values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all enum cases as array with labels.
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->label()];
        })->toArray();
    }

    /**
     * Get the display name for the enum value.
     */
    public function label(): string
    {
        return match ($this) {
            self::LEAD     => 'Lead',
            self::WORKFLOW => 'Workflow',
        };
    }
}
