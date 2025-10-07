<?php

namespace App\Providers;

use App\Services\Mail\MicrosoftGraphMailTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class MicrosoftGraphMailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Mail::extend('microsoft-graph', function (array $config = []) {
            return new MicrosoftGraphMailTransport;
        });
    }
}
