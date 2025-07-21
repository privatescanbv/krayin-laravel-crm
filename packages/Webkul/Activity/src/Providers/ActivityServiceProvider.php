<?php

namespace Webkul\Activity\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Webkul\Activity\Http\ViewComposers\ActivitiesViewComposer;
use Webkul\Activity\Services\ViewService;

class ActivityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ViewService::class, function ($app) {
            return new ViewService();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        
        // Register view composer for activities index
        View::composer('admin::activities.index', ActivitiesViewComposer::class);
    }
}
