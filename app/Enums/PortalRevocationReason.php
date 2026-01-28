<?php

namespace App\Enums;

enum PortalRevocationReason: string
{
    case Deceased = 'overleden';
    case RequestedByPatient = 'op_verzoek_patient';
    case NoLongerPatient = 'geen_patient_meer';
    case SecurityConcern = 'beveiligingsreden';
    case Other = 'anders';

    public function label(): string
    {
        return match ($this) {
            self::Deceased           => 'Patiënt overleden',
            self::RequestedByPatient => 'Op verzoek van patiënt',
            self::NoLongerPatient    => 'Geen patiënt meer',
            self::SecurityConcern    => 'Beveiligingsreden',
            self::Other              => 'Anders',
        };
    }
}
