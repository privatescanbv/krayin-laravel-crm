<?php

namespace App\Enums;

enum ResourceType: string
{
    case MRI_SCANNER = 'mri_scanner';
    case CT_SCANNER = 'ct_scanner';
    case PET_CT_SCANNER = 'pet_ct_scanner';
    case ARTSEN = 'artsen';
    case OTHER = 'other';
    case CARDIOLOGIE = 'cardiologie';

    public function label(): string
    {
        return match ($this) {
            self::MRI_SCANNER     => 'MRI scanner',
            self::CT_SCANNER      => 'CT scanner',
            self::PET_CT_SCANNER  => 'PET CT scanner',
            self::ARTSEN          => 'Artsen',
            self::OTHER           => 'Other',
            self::CARDIOLOGIE     => 'Cardiologie',
        };
    }
}