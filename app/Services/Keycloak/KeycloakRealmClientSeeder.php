<?php

namespace App\Services\Keycloak;

class KeycloakRealmClientSeeder
{
    /**
     * Get client configurations for all clients.
     *
     * @return array<string, array> Array of client configurations keyed by client ID
     */
    public static function getClientConfigs(
        string $crmExternalAppUrl,
        string $crmInternalAppUrl,
        string $patientPortalUrl,
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
            'backchannel_logout_url' => $backchannelLogoutUrl,
            'home_url'               => $crmExternalAppUrl,
            'secret_env_key'         => 'KEYCLOAK_CLIENT_SECRET',
            'secret_log_message'     => 'Keycloak client secret generated. Please update KEYCLOAK_CLIENT_SECRET in .env',
        ];

        $configs['patient-app'] = [
            'base_url'      => $patientPortalUrl,
            'redirect_uris' => [
                $patientPortalUrl.'/*',
                $patientPortalUrl.'/auth/callback',
                $patientPortalUrl.':444/auth/callback',
            ],
            'post_logout_redirect_uris' => [
                $patientPortalUrl.'/*',
            ],
            'backchannel_logout_url' => '',
            'home_url'               => $patientPortalUrl,
            //            'secret_env_key'         => 'FORMS_KEYCLOAK_CLIENT_SECRET',
            //            'secret_log_message'     => 'Keycloak Forms client secret generated. Please update FORMS_KEYCLOAK_CLIENT_SECRET in Forms .env',
        ];

        return $configs;
    }
}
