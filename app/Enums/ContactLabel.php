<?php

namespace App\Enums;

enum ContactLabel: string
{
    case Eigen = 'eigen';
    case Relatie = 'relatie';
    case Anders = 'anders';

    public static function fromOld(?string $oldLabel): self
    {
        $normalized = strtolower(trim((string) $oldLabel));

        return match ($normalized) {
            'other'                  => self::Anders,
            default                  => self::Eigen,
        };
    }

    public static function default(): self
    {
        return self::Eigen;
    }

    /**
     * Return enum options for UI: [ ['value' => 'eigen', 'label' => 'Eigen'], ... ]
     */
    public static function options(): array
    {
        return array_map(static function (self $case) {
            return [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Eigen   => 'Eigen',
            self::Relatie => 'Relatie',
            self::Anders  => 'Anders',
        };
    }
}
