<?php

namespace App\Providers;

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
                $config = $app['config']['services.keycloak'];

                // Build redirect URL with proper port
                $redirectUri = $config['redirect'];
                if (! str_starts_with($redirectUri, 'http')) {
                    $redirectUri = url($redirectUri);
                }

                $provider = $socialite->buildProvider(
                    \App\Socialite\KeycloakProvider::class,
                    [
                        'client_id'     => $config['client_id'],
                        'client_secret' => $config['client_secret'],
                        'redirect'      => $redirectUri,
                    ]
                );

                // Always use external URL for initial setup (redirects use browser)
                // Internal URL will be set explicitly in callback method
                $provider->setBaseUrl($config['base_url'] ?? 'http://localhost:8085');
                $provider->setRealm($config['realm'] ?? 'master');

                return $provider;
            }
        );
    }
}
