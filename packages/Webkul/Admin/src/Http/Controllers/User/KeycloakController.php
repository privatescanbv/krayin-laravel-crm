<?php

namespace Webkul\Admin\Http\Controllers\User;

use App\Enums\KeycloakRoles;
use App\Services\Keycloak\KeycloakService;
use Exception;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\InvalidStateException;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Middleware\Concerns\LoadsUserRoles;
use Webkul\User\Repositories\UserRepository;

class KeycloakController extends Controller
{
    use LoadsUserRoles;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected UserRepository $userRepository,
        protected KeycloakService $keycloakService
    ) {
    }

    /**
     * Redirect the user to the Keycloak authentication page.
     *
     * @return RedirectResponse
     */
    public function redirect()
    {
        // Prevent redirect loops - if already authenticated, redirect to dashboard
        if (Auth::guard('user')->check()) {
            return redirect()->route('admin.dashboard.index');
        }

        return $this->keycloakService->getRedirectUrl();
    }

    /**
     * Obtain the user information from Keycloak.
     *
     * @return RedirectResponse
     */
    public function callback()
    {
        try {
            if (Auth::guard('user')->check()) {
                return redirect()->route('admin.dashboard.index');
            }

            if (! request()->has('code')) {
                return $this->handleMissingCode();
            }

            $keycloakUser = $this->getKeycloakUser();

            $user = $this->validateUser($keycloakUser);
            if (! $user) {
                return redirect()->route('admin.session.create');
            }

            return $this->loginUser($user);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Logout from Keycloak.
     *
     * NOTE: This method is deprecated. Logout is now handled via SessionController@destroy
     * which redirects to login page with ?keycloak_logout=1 query parameter.
     * The logout iframe is then loaded silently on the login page.
     *
     * This method redirects to the standard logout route for backwards compatibility.
     *
     * @return RedirectResponse
     */
    public function logout()
    {
        // Redirect to standard logout route which handles Keycloak logout via query parameter
        return redirect()->route('admin.session.destroy');
    }

    /**
     * Handle Keycloak logout callback.
     * Keycloak redirects here after logout (to client baseUrl).
     *
     * @return RedirectResponse
     */
    public function logoutCallback()
    {
        Log::info('Keycloak logout callback', [
            'request_params' => request()->all(),
        ]);

        // Redirect to login page
        return redirect()->route('admin.session.create');
    }

    /**
     * Handle Keycloak backchannel logout.
     * Keycloak sends a POST request here to logout the user server-side.
     * This is more reliable than front-channel logout with iframes.
     *
     * @return \Illuminate\Http\Response
     */
    public function backchannelLogout()
    {
        $logoutToken = request()->input('logout_token');

        if (! $logoutToken) {
            Log::warning('Keycloak backchannel logout: missing logout_token');
            return response('', 200);
        }

        $tokenData = $this->keycloakService->decodeLogoutToken($logoutToken);

        if (! $tokenData || ! isset($tokenData['subject'])) {
            Log::warning('Keycloak backchannel logout: invalid token or missing subject');
            return response('', 200);
        }

        $user = $this->userRepository->findWhere(['keycloak_user_id' => $tokenData['subject']])->first();

        if ($user) {
            // Set cache flag to invalidate all sessions for this user
            $cacheKey = 'keycloak_logout_' . $user->id;
            cache()->put($cacheKey, true, now()->addHours(1));

            // Logout the user if they are currently logged in
            if (Auth::guard('user')->check() && Auth::guard('user')->id() === $user->id) {
                Auth::guard('user')->logout();
            }

            Log::info('User logged out via Keycloak backchannel logout', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }

        return response('', 200);
    }

    /**
     * Handle missing authorization code.
     */
    protected function handleMissingCode(): RedirectResponse
    {
        if (request()->has('error')) {
            $errorDescription = request('error_description', 'Unknown error');
            Log::error('Keycloak OAuth error', [
                'error' => request('error'),
                'error_description' => $errorDescription,
            ]);
            session()->flash('error', 'SSO authenticatie mislukt: ' . $errorDescription);
        } else {
            Log::error('Keycloak callback missing code parameter', [
                'request_params' => request()->all(),
            ]);
            session()->flash('error', 'SSO authenticatie mislukt: Geen autorisatie code ontvangen van Keycloak.');
        }

        return redirect()->route('admin.session.create');
    }

    /**
     * Get Keycloak user via Socialite.
     */
    protected function getKeycloakUser()
    {
        try {
            return $this->keycloakService->getUserViaSocialite();
        } catch (InvalidStateException $e) {
            Log::warning('Keycloak invalid state', ['error' => $e->getMessage()]);
            session()->flash('error', 'SSO authenticatie mislukt: Ongeldige sessie. Probeer opnieuw.');
            throw $e;
        } catch (ClientException $e) {
            $this->handleClientException($e);
            throw $e;
        }
    }

    /**
     * Handle ClientException from Keycloak.
     */
    protected function handleClientException(ClientException $e): void
    {
        $response = $e->getResponse();
        $statusCode = $response?->getStatusCode();
        $responseBody = $response?->getBody()->getContents();
        $requestUrl = (string) ($e->getRequest()?->getUri() ?? 'unknown');

        if ($statusCode === 401) {
            $isUserinfoCall = str_contains($requestUrl, 'userinfo');
            $isTokenCall = str_contains($requestUrl, '/token');

            Log::error('Keycloak authentication failed', [
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'request_url' => $requestUrl,
                'response_body' => $responseBody,
                'call_type' => $isUserinfoCall ? 'userinfo' : ($isTokenCall ? 'token_exchange' : 'unknown'),
                'possible_cause' => $isUserinfoCall
                    ? 'Token validation failed - issuer mismatch or token expired. Check KEYCLOAK_DOCKER_SERVICE_URL and realm frontend URL.'
                    : ($isTokenCall
                        ? 'Invalid client credentials or redirect URI mismatch. Check KEYCLOAK_CLIENT_SECRET and Valid Redirect URIs in Keycloak.'
                        : 'Authentication error'),
            ]);

            session()->flash('error', 'SSO authenticatie mislukt: Token verlopen. Probeer opnieuw.');
        } else {
            Log::error('Keycloak SSO error', [
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'request_url' => $requestUrl,
                'response_body' => $responseBody,
            ]);
        }
    }

    /**
     * Validate user for SSO login.
     *
     * @return \Webkul\User\Models\User|null
     */
    protected function validateUser($keycloakUser)
    {
        $user = $this->userRepository->findWhere(['email' => $keycloakUser->getEmail()])->first();

        if (! $user) {
            Log::error('Keycloak SSO user not found in CRM', ['email' => $keycloakUser->getEmail()]);
            session()->flash('error', 'Gebruiker niet gevonden in CRM. Neem contact op met de beheerder.');
            return null;
        }

        if (empty($user->keycloak_user_id)) {
            Log::warning('Keycloak SSO login attempted for user without keycloak_user_id', [
                'email' => $keycloakUser->getEmail(),
                'user_id' => $user->id,
            ]);
            session()->flash('error', 'Gebruiker is niet gekoppeld aan Keycloak. Neem contact op met de beheerder.');
            return null;
        }

        if ($user->keycloak_user_id !== $keycloakUser->getId()) {
            Log::error('Keycloak SSO login attempted with mismatched keycloak_user_id', [
                'email' => $keycloakUser->getEmail(),
                'user_id' => $user->id,
                'expected_keycloak_id' => $user->keycloak_user_id,
                'received_keycloak_id' => $keycloakUser->getId(),
            ]);
            session()->flash('error', 'Keycloak account komt niet overeen. Neem contact op met de beheerder.');
            return null;
        }

        if ($user->status == 0) {
            session()->flash('warning', trans('admin::app.users.activate-warning'));
            return null;
        }

        return $user;
    }

    /**
     * Login user and redirect.
     */
    protected function loginUser($user): RedirectResponse
    {
        cache()->forget('keycloak_logout_' . $user->id);
        
        // Haal rollen op en zet in sessie (niet in database)
        $roles = $this->loadUserRoles($user, $this->keycloakService);
        
        Log::debug('Keycloak roles loaded for user', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $roles,
        ]);
        
        // Check of gebruiker patient rol heeft → geen toegang tot admin panel
        if (!empty($user->keycloak_user_id)) {
            $patientRole = KeycloakRoles::Patient->value;
            if (in_array($patientRole, $roles, true)) {
                Log::warning('Patient attempted to login via SSO to admin panel', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'keycloak_user_id' => $user->keycloak_user_id,
                ]);
                
                session()->flash('error', 'U heeft geen toegang tot het admin panel. Alleen medewerkers hebben toegang.');
                
                return redirect()->route('admin.session.create');
            }
        }
        
        Auth::guard('user')->login($user, true);

        if (! bouncer()->hasPermission('dashboard')) {
            $availableNextMenu = menu()->getItems('admin')?->first();

            if (is_null($availableNextMenu)) {
                session()->flash('error', trans('admin::app.users.not-permission'));
                Auth::guard('user')->logout();
                return redirect()->route('admin.session.create');
            }

            return redirect()->to($availableNextMenu->getUrl());
        }

        session()->forget('url.intended');
        return redirect()->route('admin.dashboard.index');
    }

    /**
     * Handle general exceptions.
     */
    protected function handleException(Exception $e): RedirectResponse
    {
        Log::error('Keycloak SSO error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $errorMessage = 'SSO authenticatie mislukt.';
        if (config('app.debug')) {
            $errorMessage .= ' ' . $e->getMessage();
        }

        session()->flash('error', $errorMessage);
        Auth::guard('user')->logout();

        return redirect()->route('admin.session.create');
    }
}

