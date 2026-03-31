<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;

trait HandlesReturnUrl
{
    /**
     * Redirect to the return_url from the request if present and valid,
     * otherwise redirect to the given default route.
     *
     * Looks for return_url in both query parameters and request body.
     */
    protected function redirectWithReturnUrl(string $defaultRoute, array $defaultRouteParams = [], ?string $flashType = null, ?string $flashMessage = null): RedirectResponse
    {
        if ($flashType && $flashMessage) {
            session()->flash($flashType, $flashMessage);
        }

        $returnUrl = $this->resolveReturnUrl();

        if ($returnUrl) {
            return redirect($returnUrl);
        }

        return redirect()->route($defaultRoute, $defaultRouteParams);
    }

    /**
     * Resolve a valid return_url from the current request, or null.
     *
     * Only allows same-origin or relative paths to prevent open redirect attacks.
     */
    protected function resolveReturnUrl(): ?string
    {
        $returnUrl = request()->input('return_url') ?? request()->query('return_url');

        if (! $returnUrl) {
            return null;
        }

        // Allow relative paths (must start with /)
        if (! filter_var($returnUrl, FILTER_VALIDATE_URL)) {
            return str_starts_with($returnUrl, '/') ? $returnUrl : null;
        }

        // Absolute URL: only allow same host as the application to prevent open redirect
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $redirectHost = parse_url($returnUrl, PHP_URL_HOST);

        return ($redirectHost !== null && $redirectHost === $appHost) ? $returnUrl : null;
    }
}
