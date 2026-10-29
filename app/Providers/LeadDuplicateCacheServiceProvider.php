<?php

namespace App\Providers;

use App\Services\DuplicateFalsePositiveService;
use App\Services\LeadDuplicateCacheService;
use Illuminate\Support\ServiceProvider;
use Webkul\Lead\Repositories\LeadRepository;

class LeadDuplicateCacheServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(LeadDuplicateCacheService::class, function ($app) {
            return new LeadDuplicateCacheService(
                $app->make(LeadRepository::class),
                $app->make(DuplicateFalsePositiveService::class)
            );
        });
    }
}
