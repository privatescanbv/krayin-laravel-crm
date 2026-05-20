<?php

namespace App\Enums\Inkoop;

enum InkoopInvoiceParser: string
{
    case EVIDIA_RADIOLOGIE = 'evidia_radiologie';
    case MVZ_BOCHUM = 'mvz_bochum';
    case PROCELSIOCLINIC = 'procelsioclinic';

    public function label(): string
    {
        return match ($this) {
            self::EVIDIA_RADIOLOGIE => 'Evidia Radiologie',
            self::MVZ_BOCHUM        => 'MVZ Bochum',
            self::PROCELSIOCLINIC   => 'Operatie Kliniek',
        };
    }

    public function supplierType(): string
    {
        return match ($this) {
            self::EVIDIA_RADIOLOGIE => 'radiology',
            self::MVZ_BOCHUM        => 'cardiology',
            self::PROCELSIOCLINIC   => 'clinic',
        };
    }
}
