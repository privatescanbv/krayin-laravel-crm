<?php

namespace App\Enums;

enum ProductType: string
{
    case TOTAL_BODYSCAN = 'totalbodyscan';
    case MRI_SCAN = 'mriscan';
    case CT_SCAN = 'ctscan';
    case CARDIOLOGIE = 'cardiologie';
    case ENDOSCOPIE = 'endoscopie';
    case PETSCAN = 'petscan';
    case LABORATORIUM = 'laboratorium';
    case VERTALING = 'vertaling';
    case DIENSTEN = 'diensten';
    case OPERATIONS = 'operaties';
    case OVERIG = 'overig';

    public static function fromLabel(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if (strcasecmp($case->label(), $name) === 0) {
                return $case;
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::TOTAL_BODYSCAN => 'Total Bodyscan',
            self::MRI_SCAN       => 'MRI scan',
            self::CT_SCAN        => 'CT scan',
            self::CARDIOLOGIE    => 'Cardiologie',
            self::ENDOSCOPIE     => 'Endoscopie',
            self::PETSCAN        => 'Petscan',
            self::LABORATORIUM   => 'Laboratorium',
            self::VERTALING      => 'Vertaling',
            self::DIENSTEN       => 'Diensten',
            self::OPERATIONS     => 'Operaties',
            self::OVERIG         => 'Overig',
        };
    }

    public function isPlannable(): bool
    {
        return match ($this) {
            self::LABORATORIUM, self::VERTALING, self::DIENSTEN, self::OVERIG => false,
            default => true,
        };
    }
}
