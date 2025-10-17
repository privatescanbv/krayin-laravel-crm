<?php

namespace App\Enums;

enum ProductReports: string
{
    case RAD_MRI = 'rad_mri';
    case RAD_CT = 'rad_ct';
    case CARDIO_1 = 'cardio_1';
    case LAB_1 = 'lab_1';
    case CARDIO_LAB = 'cardio_lab';
    case GASTRO_1 = 'gastro_1';
    case COLO_1 = 'colo_1';
    case HISTO_1 = 'histo_1';
    case RAD_CARD_LAB = 'rad_card_lab';
    case RAD_PET = 'rad_pet';

    public function getLabel(): string
    {
        return match($this) {
            self::RAD_MRI => 'Radiologie MRI',
            self::RAD_CT => 'Radiologie CT',
            self::CARDIO_1 => 'Cardiologie',
            self::LAB_1 => 'Laboratoriumuitslag',
            self::CARDIO_LAB => 'Cardiologie + Radiologie',
            self::GASTRO_1 => 'Gastroscopie',
            self::COLO_1 => 'Coloscopie',
            self::HISTO_1 => 'Histologie',
            self::RAD_CARD_LAB => 'Radio/Cardio/Lab in één',
            self::RAD_PET => 'Radiologie PET',
        };
    }

    public static function getOptions(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }
        return $options;
    }
}