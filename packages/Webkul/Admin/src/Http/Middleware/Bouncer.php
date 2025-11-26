<?php

namespace Webkul\Admin\Http\Middleware;

use App\Enums\KeycloakRoles;
use App\Services\Keycloak\KeycloakService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Webkul\Admin\Http\Middleware\Concerns\LoadsUserRoles;

class Bouncer
{
    use LoadsUserRoles;

    public function __construct(
        protected KeycloakService $keycloakService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, \Closure $next, $guard = 'user')
    {
        if (! auth()->guard($guard)->check()) {
            return redirect()->route('admin.session.create');
        }

        $user = auth()->guard($guard)->user();

        /**
         * Check if user was logged out via Keycloak backchannel logout.
         * This happens when user logs out from another service (e.g., forms).
         */
        if ($user && !empty($user->keycloak_user_id)) {
            $cacheKey = 'keycloak_logout_' . $user->id;
            if (cache()->has($cacheKey)) {
                // Clear the cache flag
                cache()->forget($cacheKey);
                // Logout the user
                auth()->guard($guard)->logout();

                session()->flash('error', __('admin::app.errors.401'));

                return redirect()->route('admin.session.create');
            }
        }

        /**
         * If user status is changed by admin. Then session should be
         * logged out.
         */
        if (! (bool) $user->status) {
            auth()->guard($guard)->logout();

            session()->flash('error', __('admin::app.errors.401'));

            return redirect()->route('admin.session.create');
        }

        /**
         * Check if user has access to admin panel.
         * Patiënten (met 'patient' rol in Keycloak) hebben geen toegang.
         * Alle andere gebruikers (normale CRM gebruikers) hebben wel toegang.
         */
        if ($user) {
            if (! $this->hasEmployeeRole($user)) {
                Log::warning('Patient attempted to access admin panel', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                    'keycloak_user_id' => $user->keycloak_user_id ?? null,
                ]);

                auth()->guard($guard)->logout();

                session()->flash('error', 'U heeft geen toegang tot het admin panel. Alleen medewerkers hebben toegang.');

                return redirect()->route('admin.session.create')->setStatusCode(401);
            }
        }

        /**
         * If somehow the user deleted all permissions, then it should be
         * auto logged out and need to contact the administrator again.
         */
        if ($this->isPermissionsEmpty()) {
            auth()->guard($guard)->logout();

            session()->flash('error', __('admin::app.errors.401'));

            return redirect()->route('admin.session.create');
        }

        return $next($request);
    }


    /**
     * Check if user has the employee role (oftewel: is GEEN patiënt).
     * Rollen worden opgehaald uit sessie (gezet bij login), niet uit database.
     * 
     * Logica:
     * - Als gebruiker GEEN keycloak_user_id heeft → toegang (normale CRM gebruiker)
     * - Als gebruiker WEL keycloak_user_id heeft maar GEEN 'patient' rol → toegang (normale CRM gebruiker)
     * - Als gebruiker WEL keycloak_user_id heeft EN 'patient' rol → GEEN toegang (patiënt)
     */
    protected function hasEmployeeRole($user): bool
    {
        // Haal rollen op (uit sessie of via API)
        $roles = $this->loadUserRoles($user, $this->keycloakService);

        // Non-Keycloak users hebben altijd toegang
        if (empty($user->keycloak_user_id)) {
            return true;
        }

        // Check of gebruiker de 'patient' rol heeft → dan GEEN toegang
        $patientRole = KeycloakRoles::Patient->value;
        
        // Als gebruiker patient rol heeft, blokkeren
        if (in_array($patientRole, $roles, true)) {
            return false;
        }

        // Anders: gebruiker heeft geen patient rol → toegang (normale CRM gebruiker)
        return true;
    }

    /**
     * Check for user, if they have empty permissions or not except admin.
     *
     * @return bool
     */
    public function isPermissionsEmpty()
    {
        if (! $role = auth()->guard('user')->user()->role) {
            abort(401, 'This action is unauthorized.');
        }

        if ($role->permission_type === 'all') {
            return false;
        }

        if ($role->permission_type !== 'all' && empty($role->permissions)) {
            return true;
        }

        $this->checkIfAuthorized();

        return false;
    }

    /**
     * Check authorization.
     *
     * @return null
     */
    public function checkIfAuthorized()
    {
        $roles = acl()->getRoles();

        if (isset($roles[Route::currentRouteName()])) {
            bouncer()->allow($roles[Route::currentRouteName()]);
        }
    }
}
