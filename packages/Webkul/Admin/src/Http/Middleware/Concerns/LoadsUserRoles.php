<?php

namespace Webkul\Admin\Http\Middleware\Concerns;

use App\Services\Keycloak\KeycloakService;

trait LoadsUserRoles
{
    /**
     * Get user roles from session or fetch/set them if missing.
     * 
     * @param  \Webkul\User\Models\User  $user
     * @param  KeycloakService  $keycloakService
     * @return array<int, string> Array of role names
     */
    protected function loadUserRoles($user, KeycloakService $keycloakService): array
    {
        // Haal rollen uit sessie (gezet bij login)
        $roles = session('keycloak_roles_' . $user->id, []);

        // Als rollen niet in sessie staan:
        if (empty($roles)) {
            if (!empty($user->keycloak_user_id)) {
                // Keycloak user: haal rollen opnieuw op
                $roles = $keycloakService->getUserRoles($user->keycloak_user_id);
                session(['keycloak_roles_' . $user->id => $roles]);
            } else {
                // Non-Keycloak user: zet automatisch employee rol
                $roles = ['employee'];
                session(['keycloak_roles_' . $user->id => $roles]);
            }
        }

        return $roles;
    }
}

