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
        $this->addAuditTrailToModel(Organization::class);
        $this->addAuditTrailToModel(User::class);
        
        // Add only relations to models that have their own observers for audit trail logic
        $this->addAuditTrailRelationsToModel(Lead::class);   // LeadObserver handles audit trail
        $this->addAuditTrailRelationsToModel(Person::class); // PersonObserver handles audit trail
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
                    return $this->belongsTo(\Webkul\User\Models\User::class, 'created_by');
                };
            }

            public function updater()
            {
                return function () {
                    return $this->belongsTo(\Webkul\User\Models\User::class, 'updated_by');
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

        // Add audit trail fields to fillable for models that don't have them
        if ($modelClass === Organization::class) {
            $modelClass::mixin(new class {
                public function getFillable()
                {
                    return function () {
                        $originalFillable = ['name', 'user_id']; // Original Organization fillable
                        return array_merge($originalFillable, ['created_by', 'updated_by']);
                    };
                }
            });
        }
        
        if ($modelClass === User::class) {
            $modelClass::mixin(new class {
                public function getFillable()
                {
                    return function () {
                        $originalFillable = ['name', 'email', 'image', 'password', 'api_token', 'role_id', 'status']; // Original User fillable
                        return array_merge($originalFillable, ['created_by', 'updated_by']);
                    };
                }
            });
        }
    }

    /**
     * Add only audit trail relations to a model (without event listeners)
     */
    private function addAuditTrailRelationsToModel(string $modelClass): void
    {
        // Add audit trail relations
        $modelClass::mixin(new class {
            public function creator()
            {
                return function () {
                    return $this->belongsTo(\Webkul\User\Models\User::class, 'created_by');
                };
            }

            public function updater()
            {
                return function () {
                    return $this->belongsTo(\Webkul\User\Models\User::class, 'updated_by');
                };
            }
        });
    }
}