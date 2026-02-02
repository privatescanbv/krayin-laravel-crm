<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Enforce that a Keycloak-authenticated caller can only access their own patient routes.
 *
 * Rule:
 * - If the request uses Authorization: Bearer <token>, then the token subject (sub) must match
 *   the patient identifier in the route.
 * - If the request uses X-API-KEY authentication, we allow service-to-service access and don't enforce this.
 *
 * Note: The Keycloak token subject is set by `ApiKeyAuth` as request attribute `keycloak_token_sub`.
 */
class EnsureKeycloakPatientMatchesRoute
{
    public function handle(Request $request, Closure $next, string $routeParam = 'id')
    {
        $authHeader = (string) $request->header('Authorization', '');

        // Only enforce when a Keycloak Bearer token is used.
        if ($authHeader === '' || ! str_starts_with($authHeader, 'Bearer ')) {
            return $next($request);
        }

        $tokenSub = (string) $request->attributes->get('keycloak_token_sub', '');
        $routeValue = (string) ($request->route($routeParam) ?? '');

        // Fail closed if we can't determine the subject.
        if ($tokenSub === '' || $routeValue === '' || $tokenSub !== $routeValue) {
            abort(403);
        }

        return $next($request);
    }
}
