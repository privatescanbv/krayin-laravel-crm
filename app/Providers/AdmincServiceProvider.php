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
        // Register components in resources/views/adminc under the 'adminc' namespace
        Blade::anonymousComponentPath(resource_path('views/adminc'), 'adminc');
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }
}
