<?php

namespace App\Enums;

enum KeycloakRoles: string
{
    case Patient = 'patient';
    case Employee = 'employee';
    case Clinic = 'clinic';

    public function label(): string
    {
        return match ($this) {
            self::Patient  => 'patiënt',
            self::Employee => 'medewerker',
            self::Clinic   => 'kliniek',
        };
    }
}
