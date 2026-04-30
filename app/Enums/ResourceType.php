<?php

namespace App\Enums;

use Exception;
use Illuminate\Support\Facades\Log;

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
    public static function mapFromOrError(string $label): ResourceType
    {
        foreach (self::cases() as $case) {
            if (strtolower($case->label()) === strtolower($label)) {
                return $case;
            }
        }
        throw new Exception('Unknown resource type label: '.$label);
    }

    public static function mapFrom(string $label): ?ResourceType
    {
        try {
            return ResourceType::mapFromOrError($label);
        } catch (Exception $e) {
            Log::error("Could not map resource type by name {{ $label }}");
        }

        return null;
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

    public function isPlannable(): bool
    {
        return match ($this) {
            self::OTHER => false,
            default     => true,
        };
    }
}
