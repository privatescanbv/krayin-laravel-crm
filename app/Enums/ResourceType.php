<?php

namespace App\Enums;

use Exception;

enum ResourceType: string
{
    case MRI_SCANNER = 'mri_scanner';
    case CT_SCANNER = 'ct_scanner';
    case PET_CT_SCANNER = 'pet_ct_scanner';
    case ARTSEN = 'artsen';
    case OTHER = 'other';
    case CARDIOLOGIE = 'cardiologie';

    case RONTGEN = 'rontgen';

    /**
     * @throws Exception for none existing labels
     */
    public static function mapFrom(string $label): ResourceType
    {
        foreach (self::cases() as $case) {
            if (strtolower($case->label()) === strtolower($label)) {
                return $case;
            }
        }

        throw new Exception('Unknown resource type label: '.$label);
    }

    public function label(): string
    {
        return match ($this) {
            self::MRI_SCANNER     => 'MRI scanner',
            self::CT_SCANNER      => 'CT scanner',
            self::PET_CT_SCANNER  => 'PET CT scanner',
            self::ARTSEN          => 'Artsen',
            self::OTHER           => 'Other',
            self::CARDIOLOGIE     => 'Cardiologie',
            self::RONTGEN         => 'Rontgen',
        };
    }
}
