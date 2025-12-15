<?php

namespace App\Services\Keycloak;

use App\Enums\KeyCloakClient;

class KeycloakRealmClientSeeder
{
    public const KEY_KEYCLOAK_CLIENT = 'keycloak_client';

    /**
     * Get client configurations for all clients.
     *
     * @return array<string, array> Array of client configurations keyed by client ID
     */
    public static function getClientConfigs(
        string $crmExternalAppUrl,
        string $crmInternalAppUrl,
        string $patientPortalUrl,
        string $clinicPortalUrl,
        string $realmClientId,
    ): array {
        $configs = [];

        // CRM client configuration

        // Backchannel logout URL must use internal Docker service name
        // Keycloak calls this from inside the container, so it needs to reach the CRM container directly

        $backchannelLogoutUrl = rtrim($crmInternalAppUrl, '/').'/admin/auth/keycloak/backchannel-logout';

        $configs[$realmClientId] = [
            'base_url'      => $crmExternalAppUrl,
            'redirect_uris' => [
                $crmExternalAppUrl.'/admin/*',
            ],
            'post_logout_redirect_uris' => [
                $crmExternalAppUrl.'/admin/*',
            ],
            'backchannel_logout_url'  => $backchannelLogoutUrl,
            'home_url'                => $crmExternalAppUrl,
            'secret_log_message'      => 'Keycloak client secret generated. Please update CRM_KEYCLOAK_CLIENT_SECRET in .env',
            self::KEY_KEYCLOAK_CLIENT => KeyCloakClient::CRM,
        ];

        $configs = array_merge($configs, self::createKeycloakClientConfig(
            KeyCloakClient::PATIENT->clientId(),
            $patientPortalUrl,
            KeyCloakClient::PATIENT
        ));
        $configs = array_merge($configs, self::createKeycloakClientConfig(
            KeyCloakClient::EMPLOYEE->clientId(),
            $patientPortalUrl,
            KeyCloakClient::EMPLOYEE
        ));

        return array_merge($configs, self::createKeycloakClientConfig(
            KeyCloakClient::CLINIC->clientId(),
            $clinicPortalUrl,
            KeyCloakClient::CLINIC
        ));
    }

    public static function createKeycloakClientConfig(string $clientKey, string $url, KeyCloakClient $keyCloakClient): array
    {
        return [$clientKey => [
            'base_url'      => $url,
            'redirect_uris' => [
                $url.'/*',
                $url.'/auth/callback',
                $url.':444/auth/callback',
            ],
            'post_logout_redirect_uris' => [
                $url.'/*',
            ],
            'backchannel_logout_url'  => '',
            'home_url'                => $url,
            self::KEY_KEYCLOAK_CLIENT => $keyCloakClient,
        ],
        ];

    }
}
