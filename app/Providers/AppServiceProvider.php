<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use App\Observers\LeadObserver;
use App\Observers\OrderObserver;
use App\Observers\OrderItemObserver;
use App\Observers\PersonObserver;
use App\Observers\ResourceOrderItemObserver;
use App\Observers\SalesLeadObserver;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
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
        //
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
    }
}
