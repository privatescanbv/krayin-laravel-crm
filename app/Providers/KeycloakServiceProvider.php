<?php

namespace App\Providers;

use App\Socialite\KeycloakProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;

class KeycloakServiceProvider extends ServiceProvider
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
        $socialite = $this->app->make(SocialiteFactory::class);

        $socialite->extend(
            'keycloak',
            function ($app) use ($socialite) {
                // Build redirect URL with proper port
                $redirectUri = config('services.keycloak.redirect');
                if (! str_starts_with($redirectUri, 'http')) {
                    $redirectUri = url($redirectUri);
                }

                $provider = $socialite->buildProvider(
                    KeycloakProvider::class,
                    [
                        'client_id'     => config('services.keycloak.client_id'),
                        'client_secret' => config('services.keycloak.client_secret'),
                        'redirect'      => $redirectUri,
                    ]
                );

                return $provider;
            }
        );
    }
}
