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
    case OVERIG = 'overig';

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
            self::OVERIG         => 'Overig',
        };
    }
}
