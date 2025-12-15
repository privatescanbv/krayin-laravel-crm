<?php

namespace App\Enums;

use InvalidArgumentException;

enum KeyCloakClient: string
{
    case PATIENT = 'patient';
    case CLINIC = 'clinic';
    case EMPLOYEE = 'employee';
    case CRM = 'crm';

    public static function mapFrom(mixed $clientId): KeyCloakClient
    {
        return match (strtolower($clientId)) {
            self::PATIENT->value  => self::PATIENT,
            self::CLINIC->value   => self::CLINIC,
            self::EMPLOYEE->value => self::EMPLOYEE,
            self::CRM->value      => self::CRM,
            default               => throw new InvalidArgumentException(
                "Onbekende Keycloak client: {$clientId}"
            ),
        };

    }

    public function envKeySecret(): string
    {
        return match ($this) {
            self::PATIENT     => 'PATIENT_PORTAL_KEYCLOAK_CLIENT_SECRET',
            self::CLINIC      => 'CLINIC_PORTAL_KEYCLOAK_CLIENT_SECRET',
            self::EMPLOYEE    => 'EMPLOYEE_PORTAL_KEYCLOAK_CLIENT_SECRET',
            self::CRM         => 'CRM_KEYCLOAK_CLIENT_SECRET',
        };
    }

    public function configKeySecret(): string
    {
        return match ($this) {
            self::PATIENT     => 'services.keycloak.portal.patient.secret',
            self::CLINIC      => 'services.keycloak.portal.clinic.secret',
            self::EMPLOYEE    => 'services.keycloak.portal.employee.secret',
            self::CRM         => 'services.keycloak.client_secret',
        };
    }

    public function clientId(): string
    {
        return match ($this) {
            self::PATIENT     => 'patient-app',
            self::CLINIC      => 'clinic-app',
            self::EMPLOYEE    => 'employee-app',
            self::CRM         => 'crm-app',
        };
    }
}
