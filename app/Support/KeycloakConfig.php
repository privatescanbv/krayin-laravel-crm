<?php

namespace App\Support;

/**
 * Central place for Keycloak base URLs and realm configuration.
 *
 * Hiermee voorkomen we duplicatie van:
 * - base_url_external / base_url_internal
 * - realm
 * - het opbouwen van interne/externe URLs
 */
class KeycloakConfig
{
    /**
     * Externe base URL (voor browser redirects).
     */
    public static function externalBaseUrl(): string
    {
        return rtrim(config('services.keycloak.base_url_external', 'http://no_keycloak_url_provided'), '/');
    }

    /**
     * Interne base URL (voor server‑side API calls vanuit containers).
     */
    public static function internalBaseUrl(): string
    {
        return rtrim(config('services.keycloak.base_url_internal', 'http://keycloak.local:8080'), '/');
    }

    /**
     * Realm naam.
     */
    public static function realm(): string
    {
        return config('services.keycloak.realm', 'crm');
    }

    /**
     * Volledige interne URL op basis van het interne base URL.
     */
    public static function internalUrl(string $path): string
    {
        $base = self::internalBaseUrl();
        $path = ltrim($path, '/');

        return $base.'/'.$path;
    }

    /**
     * Volledige externe URL op basis van het externe base URL.
     */
    public static function externalUrl(string $path): string
    {
        $base = self::externalBaseUrl();
        $path = ltrim($path, '/');

        return $base.'/'.$path;
    }
}
