<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

class TelescopeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Check if Telescope is enabled in config
        if (! config('telescope.enabled', false)) {
            return;
        } elseif (! class_exists('Laravel\Telescope\Telescope')) {
            logger()->error('Telescope is enabled in config but the package is not installed.');

            return;
        }

        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only boot Telescope if it's enabled and in local environment
        if (! config('telescope.enabled', false)) {
            return;
        }

        if (! $this->app->environment('local')) {
            return;
        }

        $this->gate();
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if (! class_exists('Laravel\Telescope\Telescope')) {
            return;
        }

        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        if (! class_exists('Laravel\Telescope\Telescope')) {
            return;
        }

        Gate::define('viewTelescope', function ($user) {
            return in_array($user->email, [
                'mark.bulthuis@privatescan.nl',
            ]);
        });
    }
}
