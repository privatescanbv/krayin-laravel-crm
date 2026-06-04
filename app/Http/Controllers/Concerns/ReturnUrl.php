<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

final class ReturnUrl
{
    /**
     * Build a return_url for the current request, optionally with a tab hash fragment.
     *
     * The browser never sends hash fragments to the server, so they must be appended here.
     */
    public static function currentWithHash(?string $hash = null, ?Request $request = null): string
    {
        $request ??= request();

        $url = $request->getRequestUri();

        if ($hash === null || $hash === '') {
            return $url;
        }

        return $url.(str_starts_with($hash, '#') ? $hash : '#'.$hash);
    }

    /**
     * Resolve a valid return_url from the request, or null.
     *
     * Only allows same-origin or relative paths to prevent open redirect attacks.
     */
    public static function resolveFromRequest(?Request $request = null): ?string
    {
        $request ??= request();

        $returnUrl = $request->input('return_url') ?? $request->query('return_url');

        if (! is_string($returnUrl) || $returnUrl === '') {
            return null;
        }

        return self::isAllowed($returnUrl) ? $returnUrl : null;
    }

    /**
     * Append a return_url query parameter to the given URL.
     */
    public static function appendQuery(string $url, string $returnUrl): string
    {
        if ($returnUrl === '') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'return_url='.urlencode($returnUrl);
    }

    /**
     * Append return_url from the request when valid, otherwise return the URL unchanged.
     */
    public static function appendResolvedQuery(string $url, ?Request $request = null): string
    {
        $returnUrl = self::resolveFromRequest($request);

        return $returnUrl ? self::appendQuery($url, $returnUrl) : $url;
    }

    /**
     * Build a ?return_url=… or &return_url=… suffix for links and forms.
     */
    public static function querySuffix(?string $returnUrl): string
    {
        return $returnUrl ? self::appendQuery('', $returnUrl) : '';
    }

    public static function isAllowed(string $returnUrl): bool
    {
        if (! filter_var($returnUrl, FILTER_VALIDATE_URL)) {
            return str_starts_with($returnUrl, '/');
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $redirectHost = parse_url($returnUrl, PHP_URL_HOST);

        return $redirectHost !== null && $redirectHost === $appHost;
    }
}
