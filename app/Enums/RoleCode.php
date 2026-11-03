<?php

namespace App\Enums;

/**
 * Fixed CRM roles with special behaviour, stored in roles.code (ids and names differ per environment).
 */
enum RoleCode: string
{
    case ARTS = 'arts';                             // linked to an ARTSEN resource
    case KLINIEK_MEDEWERKER = 'kliniek_medewerker'; // linked to exactly one clinic

    public function label(): string
    {
        return match ($this) {
            self::ARTS               => 'Arts',
            self::KLINIEK_MEDEWERKER => 'Kliniek medewerker',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ARTS               => 'Arts met toegang tot de clinic-portal',
            self::KLINIEK_MEDEWERKER => 'Kliniek medewerker met toegang tot de clinic-portal',
        };
    }

    /**
     * Both roles log in to the clinic-portal only.
     */
    public function keycloakRole(): KeycloakRoles
    {
        return KeycloakRoles::Clinic;
    }
}
