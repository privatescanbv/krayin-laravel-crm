<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class AuditTrailServiceProvider extends ServiceProvider
{
    /**
     * Models that need full audit trail functionality (event listeners + relations)
     */
    private const FULL_AUDIT_MODELS = [
        Organization::class,
        User::class,
    ];

    /**
     * Models that only need relations (have their own observers for audit trail logic)
     */
    private const RELATIONS_ONLY_MODELS = [
        Lead::class,   // LeadObserver handles audit trail
        Person::class, // PersonObserver handles audit trail
    ];

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
        // Add full audit trail functionality
        foreach (self::FULL_AUDIT_MODELS as $modelClass) {
            $this->addAuditTrailToModel($modelClass);
        }

        // Add only relations for models with existing observers
        foreach (self::RELATIONS_ONLY_MODELS as $modelClass) {
            $this->addAuditTrailRelations($modelClass);
        }
    }

    /**
     * Add full audit trail functionality to a model (relations + event listeners + fillable)
     */
    private function addAuditTrailToModel(string $modelClass): void
    {
        // Add relations
        $this->addAuditTrailRelations($modelClass);

        // Add event listeners for automatic audit trail
        $this->addAuditTrailEventListeners($modelClass);

        // Add fillable fields for models that need them
        $this->addAuditTrailFillable($modelClass);
    }

    /**
     * Add audit trail relations to a model
     */
    private function addAuditTrailRelations(string $modelClass): void
    {
        $modelClass::mixin(new class
        {
            public function creator()
            {
                return function () {
                    return $this->belongsTo(User::class, 'created_by');
                };
            }

            public function updater()
            {
                return function () {
                    return $this->belongsTo(User::class, 'updated_by');
                };
            }
        });
    }

    /**
     * Add audit trail event listeners to a model
     */
    private function addAuditTrailEventListeners(string $modelClass): void
    {
        $modelClass::creating(function ($model) {
            $auth = auth()->guard('user')->check() ? auth()->guard('user') : auth();
            if ($auth->check()) {
                $model->created_by = $auth->id();
                $model->updated_by = $auth->id();
            }
        });

        $modelClass::updating(function ($model) {
            $auth = auth()->guard('user')->check() ? auth()->guard('user') : auth();
            if ($auth->check()) {
                $model->updated_by = $auth->id();
            }
        });
    }

    /**
     * Add audit trail fields to fillable array for models that don't have them
     */
    private function addAuditTrailFillable(string $modelClass): void
    {
        if ($modelClass === Organization::class) {
            $this->addOrganizationFillable($modelClass);
        } elseif ($modelClass === User::class) {
            $this->addUserFillable($modelClass);
        }
    }

    /**
     * Add fillable fields to Organization model
     */
    private function addOrganizationFillable(string $modelClass): void
    {
        $modelClass::mixin(new class
        {
            public function getFillable()
            {
                return function () {
                    return ['name', 'user_id', 'created_by', 'updated_by'];
                };
            }
        });
    }

    /**
     * Add fillable fields to User model
     */
    private function addUserFillable(string $modelClass): void
    {
        $modelClass::mixin(new class
        {
            public function getFillable()
            {
                return function () {
                    return ['name', 'email', 'image', 'password', 'api_token', 'role_id', 'status', 'created_by', 'updated_by'];
                };
            }
        });
    }
}
