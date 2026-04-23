<?php

namespace App\Providers;

use App\Contracts\Api\ApiHttpTrafficLogger;
use App\Http\Middleware\CanInstall;
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
use App\Services\Api\DefaultApiHttpTrafficLogger;
use App\Services\OrderCheckService;
use App\Services\Storage\DocumentStorage;
use App\Services\Storage\LaravelDocumentStorage;
use App\Support\IdeHelper\SocialiteFacadeForIdeHelper;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Socialite;
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
        // Workaround for `ide-helper:generate` crashing on Socialite::fake() signature.
        // Only active while running the ide-helper generation command.
        if ($this->app->runningInConsole()) {
            $argv = $_SERVER['argv'] ?? [];

            if (in_array('ide-helper:generate', $argv, true)) {
                // Avoid autoloading the real classes before aliasing.
                if (! class_exists(Socialite::class, false)) {
                    class_alias(SocialiteFacadeForIdeHelper::class, Socialite::class);
                }

                if (! class_exists(\Laravel\Socialite\Facades\Socialite::class, false)) {
                    class_alias(SocialiteFacadeForIdeHelper::class, \Laravel\Socialite\Facades\Socialite::class);
                }
            }
        }

        // Compatibility alias: the Webkul Installer package was removed; map the old class
        // name so existing test files that import it continue to resolve correctly.
        if (! class_exists(\Webkul\Installer\Http\Middleware\CanInstall::class, false)) {
            class_alias(CanInstall::class, \Webkul\Installer\Http\Middleware\CanInstall::class);
        }

        $this->app->singleton(OrderCheckService::class);
        $this->app->bind(DocumentStorage::class, LaravelDocumentStorage::class);

        $this->app->singleton(ApiHttpTrafficLogger::class, DefaultApiHttpTrafficLogger::class);
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
