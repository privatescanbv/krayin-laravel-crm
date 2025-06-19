<?php

namespace App\Providers;

use App\Observers\AttributeValueObserver;
use App\Observers\LeadObserver;
use Illuminate\Support\ServiceProvider;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Lead\Models\Lead;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Lead::observe(LeadObserver::class);
        AttributeValue::observe(AttributeValueObserver::class);
    }
}
