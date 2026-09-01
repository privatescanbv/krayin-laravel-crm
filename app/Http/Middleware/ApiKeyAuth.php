<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-KEY');

        // First, allow access when a valid X-API-KEY header is provided (existing behaviour)
        if ($apiKey) {
            $validApiKeys = config('api.keys', []);

            $isValidKey = false;

            foreach ($validApiKeys as $validApiKey) {
                if (hash_equals((string) $validApiKey, (string) $apiKey)) {
                    $isValidKey = true;

                    break;
                }
            }

            if ($isValidKey) {
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
        //
        // IMPORTANT: A Keycloak bearer token only proves who the caller is, not what they may
        // access. Only routes that additionally scope access to that specific subject (e.g. the
        // `patient/{id}` routes protected by the `patient.self:id` middleware, or
        // `keycloak/persons/{keycloakUserId}` with `patient.self:keycloakUserId`) are safe to
        // expose to Keycloak-authenticated callers. All other routes (leads, sales-leads,
        // webhooks, etc.) are service-to-service only and must require a trusted X-API-KEY.
        $authHeader = $request->header('Authorization');

        if (! $this->allowsKeycloakBearerFallback($request)) {
            Log::warning('ApiKeyAuth: unauthorized request - non-patient route requires X-API-KEY', [
                'path' => $request->path(),
            ]);

            return response()->json([
                'error'   => 'Unauthorized',
                'message' => 'This endpoint requires a valid X-API-KEY',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $accessToken = trim(substr($authHeader, strlen('Bearer ')));

            if ($accessToken !== '') {
                try {
                    // Will throw on invalid/expired token; we don't actually need the user object here,
                    // only the fact that the token is accepted by Keycloak.
                    /** @var AbstractProvider $provider */
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

    /**
     * Routes where a Keycloak bearer token may substitute for X-API-KEY.
     * Each route must enforce that the token subject matches the requested resource.
     */
    private function allowsKeycloakBearerFallback(Request $request): bool
    {
        return $request->is('api/patient/*')
            || $request->is('api/keycloak/persons/*');
    }
}
