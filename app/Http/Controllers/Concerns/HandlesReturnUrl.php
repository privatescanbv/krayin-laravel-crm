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

        if ($redirect = $this->redirectIfResolvedReturnUrl()) {
            return $redirect;
        }

        return redirect()->route($defaultRoute, $defaultRouteParams);
    }

    /**
     * Redirect to a named route, preserving a valid return_url from the request.
     */
    protected function redirectToRoutePreservingReturnUrl(string $routeName, mixed $parameters = []): RedirectResponse
    {
        return redirect(ReturnUrl::appendResolvedQuery(route($routeName, $parameters)));
    }

    /**
     * Redirect to return_url when present and valid, otherwise null.
     */
    protected function redirectIfResolvedReturnUrl(): ?RedirectResponse
    {
        $returnUrl = $this->resolveReturnUrl();

        return $returnUrl ? redirect($returnUrl) : null;
    }

    /**
     * Resolve a valid return_url from the current request, or null.
     */
    protected function resolveReturnUrl(): ?string
    {
        return ReturnUrl::resolveFromRequest();
    }
}
