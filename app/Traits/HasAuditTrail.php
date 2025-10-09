<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasAuditTrail
{
    /**
     * Boot the trait - automatically set audit trail fields
     */
    protected static function bootHasAuditTrail(): void
    {
        static::creating(function (Model $model) {
            $auth = auth()->guard('user')->check() ? auth()->guard('user') : auth();
            if ($auth->check()) {
                $model->created_by = $auth->id();
                $model->updated_by = $auth->id();
            }
        });

        static::updating(function (Model $model) {
            $auth = auth()->guard('user')->check() ? auth()->guard('user') : auth();
            if ($auth->check()) {
                $model->updated_by = $auth->id();
            }
        });

        static::saving(function (Model $model) {
            // Only set updated_by if the model is being updated (not created)
            if ($model->exists) {
                $auth = auth()->guard('user')->check() ? auth()->guard('user') : auth();
                if ($auth->check()) {
                    $model->updated_by = $auth->id();
                }
            }
        });
    }

    /**
     * Get the user who created this record
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\Webkul\User\Models\User::class, 'created_by');
    }

    /**
     * Get the user who last updated this record
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(\Webkul\User\Models\User::class, 'updated_by');
    }

    /**
     * Get the audit trail fields that should be added to fillable
     */
    public function getAuditTrailFillable(): array
    {
        return ['created_by', 'updated_by'];
    }

    /**
     * Get the audit trail fields that should be cast
     */
    public function getAuditTrailCasts(): array
    {
        return [
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }
}
