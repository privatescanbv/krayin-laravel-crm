<?php

namespace App\Providers;

use App\Observers\LeadObserver;
use Illuminate\Support\ServiceProvider;
use Webkul\Lead\Models\Lead;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Lead::observe(LeadObserver::class);
    }
}
