<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * This service provider is used to register the components in the resources/views/admin directory.
 */
class AdmincServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register components in resources/views/admin under the 'adminc' namespace
        Blade::anonymousComponentPath(resource_path('views/admin'), 'adminc');
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }
}
