<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-KEY');

        // First, allow access when a valid X-API-KEY header is provided (existing behaviour)
        if ($apiKey) {
            $validApiKeys = config('api.keys', []);

            if (! empty($validApiKeys) && in_array($apiKey, $validApiKeys)) {
                return $next($request);
            }

            Log::warning('ApiKeyAuth: invalid API key', ['provided_api_key' => $apiKey]);

            return response()->json([
                'error'   => 'Invalid API key',
                'message' => 'The provided API key is not valid',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // If no API key is present, fall back to Keycloak bearer token authentication.
        // This is used by external applications (e.g. Forms) that authenticate via Keycloak
        // and call the CRM API with an Authorization: Bearer <token> header.
        $authHeader = $request->header('Authorization');

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $accessToken = trim(substr($authHeader, strlen('Bearer ')));

            if ($accessToken !== '') {
                try {
                    // Will throw on invalid/expired token; we don't actually need the user object here,
                    // only the fact that the token is accepted by Keycloak.
                    /** @var \Laravel\Socialite\Two\AbstractProvider $provider */
                    $provider = Socialite::driver('keycloak');
                    /** @var SocialiteUser $keycloakUser */
                    $keycloakUser = $provider->userFromToken($accessToken);

                    // Make the Keycloak subject available for downstream authorization checks.
                    $keycloakUserId = method_exists($keycloakUser, 'getId')
                        ? $keycloakUser->getId()
                        : ($keycloakUser->id ?? null);

                    if (is_string($keycloakUserId) && $keycloakUserId !== '') {
                        $request->attributes->set('keycloak_token_sub', $keycloakUserId);
                    }

                    //                    Log::debug('ApiKeyAuth: valid Keycloak bearer token accepted');

                    return $next($request);
                } catch (Throwable $e) {
                    Log::warning('ApiKeyAuth: invalid Keycloak token', [
                        'error_class' => get_class($e),
                        'error'       => $e->getMessage(),
                    ]);

                    return response()->json([
                        'error'   => 'Invalid Keycloak token',
                        'message' => 'The provided Keycloak access token is invalid or expired',
                    ], Response::HTTP_UNAUTHORIZED);
                }
            }
        }

        // Neither a valid API key nor a valid Keycloak bearer token was provided.
        Log::warning('ApiKeyAuth: unauthorized request - no valid API key or Keycloak token');

        return response()->json([
            'error'   => 'Unauthorized',
            'message' => 'Provide a valid API key in X-API-KEY or a valid Keycloak Bearer token',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
