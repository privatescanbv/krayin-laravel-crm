<?php

namespace Webkul\Product\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Webkul\Product\Repositories\ProductGroupRepository;

class ProductServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ProductGroupRepository::class, function ($app) {
            return new ProductGroupRepository($app);
        });
    }
}
