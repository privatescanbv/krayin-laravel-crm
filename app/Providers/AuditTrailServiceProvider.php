<?php

namespace App\Providers;

use App\Traits\HasAuditTrail;
use Illuminate\Support\ServiceProvider;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
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
        // Add audit trail functionality to Webkul models
        $this->addAuditTrailToModel(User::class);
        $this->addAuditTrailToModel(Lead::class);
        $this->addAuditTrailToModel(Person::class);
        $this->addAuditTrailToModel(Organization::class);
    }

    /**
     * Add audit trail functionality to a model
     */
    private function addAuditTrailToModel(string $modelClass): void
    {
        // Add audit trail relations
        $modelClass::mixin(new class {
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
        $modelClass::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
                $model->updated_by = auth()->id();
            }
        });

        $modelClass::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        // Add audit trail fields to fillable for Organization model (if not already present)
        if ($modelClass === Organization::class) {
            $modelClass::creating(function ($model) {
                // Ensure audit trail fields are fillable
                $currentFillable = $model->getFillable();
                if (!in_array('created_by', $currentFillable)) {
                    $model->fillable(array_merge($currentFillable, ['created_by', 'updated_by']));
                }
            });
        }
    }
}