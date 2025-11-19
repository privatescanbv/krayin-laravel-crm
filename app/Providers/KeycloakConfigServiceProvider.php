<?php

namespace App\Providers;

use App\Services\KeycloakConfigService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class KeycloakConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only run if Keycloak is configured
        if (empty(config('services.keycloak.client_id'))) {
            return;
        }

        // Skip during tests to avoid unnecessary API calls
        if (app()->runningUnitTests()) {
            return;
        }

        // Only run during web requests (not console commands)
        // Console commands should use the artisan command explicitly
        if (app()->runningInConsole()) {
            return;
        }

        try {
            $configService = app(KeycloakConfigService::class);
            $results = $configService->syncConfig();

            if (! empty($results['errors'])) {
                Log::warning('Keycloak config sync failed during boot', [
                    'errors' => $results['errors'],
                ]);
            } elseif ($results['realm_created']) {
                Log::info('Keycloak config sync completed during boot', [
                    'realm_created'  => $results['realm_created'],
                    'realm_exists'   => $results['realm_exists'],
                    'client_created' => $results['client_created'],
                    'client_exists'  => $results['client_exists'],
                    'client_updated' => $results['client_updated'],
                ]);
            }
        } catch (Exception $e) {
            // Don't fail boot if Keycloak is not available
            Log::warning('Keycloak config sync exception during boot', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
