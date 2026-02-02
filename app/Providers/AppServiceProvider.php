<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PatientMessage;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use App\Observers\ActivityObserver;
use App\Observers\LeadObserver;
use App\Observers\OrderItemObserver;
use App\Observers\OrderObserver;
use App\Observers\PatientMessageObserver;
use App\Observers\PersonObserver;
use App\Observers\ResourceOrderItemObserver;
use App\Observers\SalesLeadObserver;
use App\Observers\UserObserver;
use App\Services\OrderCheckService;
use App\Services\Storage\DocumentStorage;
use App\Services\Storage\LaravelDocumentStorage;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OrderCheckService::class);
        $this->app->bind(DocumentStorage::class, LaravelDocumentStorage::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Lead::observe(LeadObserver::class);
        Person::observe(PersonObserver::class);
        SalesLead::observe(SalesLeadObserver::class);
        Order::observe(OrderObserver::class);
        OrderItem::observe(OrderItemObserver::class);
        ResourceOrderItem::observe(ResourceOrderItemObserver::class);
        User::observe(UserObserver::class);
        PatientMessage::observe(PatientMessageObserver::class);
        Activity::observe(ActivityObserver::class);

        // Register custom validation rules
        Validator::extend('active_user', function ($attribute, $value, $parameters, $validator) {
            if ($value === null || $value === '') {
                return true; // Allow null/empty values
            }

            $user = User::find($value);

            return $user && $user->status == 1;
        });

        Validator::replacer('active_user', function ($message, $attribute, $rule, $parameters) {
            return 'De geselecteerde gebruiker is niet actief.';
        });

        // Register custom Blade components
        //        Blade::componentNamespace('App\\View\\Components\\Adminc', 'adminc');
        Blade::anonymousComponentPath(resource_path('views/adminc'), 'adminc');

        // Register adminc view namespace
        $this->loadViewsFrom(resource_path('views/adminc'), 'adminc');
    }
}
