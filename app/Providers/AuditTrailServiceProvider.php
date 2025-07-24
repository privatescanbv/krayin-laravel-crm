<?php

namespace App\Providers;

use App\Traits\HasAuditTrail;
use Illuminate\Support\ServiceProvider;
use Webkul\User\Models\User;

class AuditTrailServiceProvider extends ServiceProvider
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
        // Add audit trail functionality to Webkul User model
        User::mixin(new class {
            public function creator()
            {
                return function () {
                    return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
                };
            }

            public function updater()
            {
                return function () {
                    return $this->belongsTo(config('auth.providers.users.model'), 'updated_by');
                };
            }
        });

        // Add audit trail boot functionality
        User::creating(function ($user) {
            if (auth()->check()) {
                $user->created_by = auth()->id();
                $user->updated_by = auth()->id();
            }
        });

        User::updating(function ($user) {
            if (auth()->check()) {
                $user->updated_by = auth()->id();
            }
        });
    }
}