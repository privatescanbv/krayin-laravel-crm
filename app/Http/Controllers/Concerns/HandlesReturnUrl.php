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
     */
    protected function resolveReturnUrl(): ?string
    {
        $returnUrl = request()->input('return_url') ?? request()->query('return_url');

        if ($returnUrl && filter_var($returnUrl, FILTER_VALIDATE_URL)) {
            return $returnUrl;
        }

        return null;
    }
}
