<?php

namespace Webkul\Admin\Http\Controllers\User;

use App\Services\KeycloakService;
use Exception;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\InvalidStateException;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\User\Repositories\UserRepository;

class KeycloakController extends Controller
{
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
     * @return \Illuminate\Http\RedirectResponse
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
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback()
    {
        try {
            // Prevent redirect loops - if already authenticated, redirect to dashboard
            if (Auth::guard('user')->check()) {
                return redirect()->route('admin.dashboard.index');
            }

            // Check if we have the authorization code
            if (! request()->has('code')) {
                if (request()->has('error')) {
                    $error = request('error');
                    $errorDescription = request('error_description', 'Unknown error');
                    Log::error('Keycloak OAuth error', [
                        'error' => $error,
                        'error_description' => $errorDescription,
                    ]);
                    session()->flash('error', 'SSO authenticatie mislukt 4: ' . $errorDescription);
                } else {
                    Log::error('Keycloak callback missing code parameter', [
                        'request_params' => request()->all(),
                    ]);
                    session()->flash('error', 'SSO authenticatie mislukt: Geen autorisatie code ontvangen van Keycloak.');
                }

                return redirect()->route('admin.session.create');
            }

            try {
                // Get Keycloak user via Socialite
                $keycloakUser = $this->keycloakService->getUserViaSocialite();
            } catch (InvalidStateException $e) {
                // Invalid state - likely expired or reused authorization code
                // Don't redirect to redirect again (would cause loop), go to login page instead
                Log::warning('Keycloak invalid state, redirecting to login page', [
                    'error' => $e->getMessage(),
                ]);
                session()->flash('error', 'SSO authenticatie mislukt: Ongeldige sessie. Probeer opnieuw.');
                return redirect()->route('admin.session.create');
            } catch (ClientException $e) {
                $response = $e->getResponse();
                $statusCode = $response ? $response->getStatusCode() : null;
                $responseBody = $response ? $response->getBody()->getContents() : null;
                $requestUrl = $e->getRequest() ? (string) $e->getRequest()->getUri() : 'unknown';

                // Handle 401 Unauthorized - token expired or invalid
                if ($statusCode === 401) {
                    $isUserinfoCall = strpos($requestUrl, 'userinfo') !== false;
                    $isTokenCall = strpos($requestUrl, '/token') !== false;

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
                    return redirect()->route('admin.session.create');
                }

                // Other client errors
                Log::error('Keycloak SSO2 error', [
                    'error' => $e->getMessage(),
                    'status_code' => $statusCode,
                    'request_url' => $requestUrl,
                    'response_body' => $responseBody,
                ]);

                throw $e;
            }

            // Find or create user
            $user = $this->userRepository->findWhere(['email' => $keycloakUser->getEmail()])->first();

            // Check if user is active
            if ($user->status == 0) {
                session()->flash('warning', trans('admin::app.users.activate-warning'));

                return redirect()->route('admin.session.create');
            }

            // Clear logout flag if it exists
            cache()->forget('keycloak_logout_' . $user->id);

            // Login the user
            Auth::guard('user')->login($user, true);

            // Check permissions
            if (! bouncer()->hasPermission('dashboard')) {
                $availableNextMenu = menu()->getItems('admin')?->first();

                if (is_null($availableNextMenu)) {
                    session()->flash('error', trans('admin::app.users.not-permission'));

                    Auth::guard('user')->logout();

                    return redirect()->route('admin.session.create');
                }

                return redirect()->to($availableNextMenu->getUrl());
            }

            // Clear any intended URL that might point to logout or other incorrect routes
            session()->forget('url.intended');

            // Redirect directly to dashboard after successful SSO login
            return redirect()->route('admin.dashboard.index');
        } catch (Exception $e) {
            Log::error('Keycloak SSO 3 error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMessage = 'SSO authenticatie mislukt ..';
            if (config('app.debug')) {
                $errorMessage .= ': ' . $e->getMessage();
            }

            session()->flash('error', $errorMessage);
            Auth::guard('user')->logout();
            return redirect()->route('admin.session.create');
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
     * @return \Illuminate\Http\RedirectResponse
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
     * @return \Illuminate\Http\RedirectResponse
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
}

