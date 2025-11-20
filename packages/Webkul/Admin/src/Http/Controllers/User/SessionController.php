<?php

namespace Webkul\Admin\Http\Controllers\User;

use App\Services\KeycloakService;
use Illuminate\Support\Facades\Log;
use Webkul\Admin\Http\Controllers\Controller;

class SessionController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected KeycloakService $keycloakService
    ) {
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        if (auth()->guard('user')->check()) {
            return redirect()->route('admin.dashboard.index');
        } else {
            // Set intended URL, but exclude logout and other auth routes
            $previousUrl = url()->previous();
            $excludedRoutes = ['logout', 'login', 'auth/keycloak'];

            if (strpos($previousUrl, 'admin') !== false) {
                // Check if previous URL is not a logout or auth route
                $isExcluded = false;
                foreach ($excludedRoutes as $excluded) {
                    if (strpos($previousUrl, $excluded) !== false) {
                        $isExcluded = true;
                        break;
                    }
                }

                if (!$isExcluded) {
                    $intendedUrl = $previousUrl;
                } else {
                    $intendedUrl = route('admin.dashboard.index');
                }
            } else {
                $intendedUrl = route('admin.dashboard.index');
            }

            session()->put('url.intended', $intendedUrl);

            return view('admin::sessions.login');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        $this->validate(request(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (! auth()->guard('user')->attempt(request(['email', 'password']), request('remember'))) {
            session()->flash('error', trans('admin::app.users.login-error'));

            return redirect()->back();
        }

        if (auth()->guard('user')->user()->status == 0) {
            session()->flash('warning', trans('admin::app.users.activate-warning'));

            auth()->guard('user')->logout();

            return redirect()->route('admin.session.create');
        }

        if (! bouncer()->hasPermission('dashboard')) {
            $availableNextMenu = menu()->getItems('admin')?->first();

            if (is_null($availableNextMenu)) {
                session()->flash('error', trans('admin::app.users.not-permission'));

                auth()->guard('user')->logout();

                return redirect()->route('admin.session.create');
            }

            return redirect()->to($availableNextMenu->getUrl());
        }

        return redirect()->intended(route('admin.dashboard.index'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy()
    {
        $user = auth()->guard('user')->user();
        $isSSOUser = $user && !empty($user->keycloak_user_id) && config('services.keycloak.client_id');

        // Logout from Laravel first
        auth()->guard('user')->logout();

        // If SSO user, trigger Keycloak logout via redirect
        // Keycloak will handle logout and call backchannel logout endpoint if configured
        if ($isSSOUser) {
            $logoutUrl = $this->keycloakService->getLogoutUrl();
            $clientId = $this->keycloakService->getClientId();

            // Try logout with only client_id first (simpler, redirects to client base URL)
            $fullLogoutUrl = $logoutUrl . '?client_id=' . urlencode($clientId);

            Log::debug('Redirecting to Keycloak logout', [
                'logout_url' => $fullLogoutUrl,
                'client_id' => $clientId,
                'note' => 'Using logout with client_id only - Keycloak will redirect to client base URL',
            ]);

            return redirect($fullLogoutUrl);
        }

        // Redirect directly to login for non-SSO users
        return redirect()->route('admin.session.create');
    }
}
