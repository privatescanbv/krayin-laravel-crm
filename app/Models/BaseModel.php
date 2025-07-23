<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use HasAuditTrail;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Initialize the model
     */
    protected function initializeBaseModel(): void
    {
        // Merge audit trail fields with existing fillable fields
        $this->fillable = array_merge($this->fillable ?? [], $this->getAuditTrailFillable());
        
        // Merge audit trail casts with existing casts
        $this->casts = array_merge($this->casts ?? [], $this->getAuditTrailCasts());
    }

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();
        
        // Ensure audit trail is initialized for all models extending this base
        static::creating(function ($model) {
            $model->initializeBaseModel();
        });
    }
}