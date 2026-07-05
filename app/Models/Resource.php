<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * @mixin IdeHelperResource
 */
class Resource extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'resources';

    protected $fillable = [
        'external_id',
        'name',
        'resource_type_id',
        'clinic_department_id',
        'is_active',
        'allow_outside_availability',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'clinic_department_id'       => 'integer',
        'resource_type_id'           => 'integer',
        'is_active'                  => 'boolean',
        'allow_outside_availability' => 'boolean',
        'created_by'                 => 'integer',
        'updated_by'                 => 'integer',
    ];

    public function clinicDepartment(): BelongsTo
    {
        return $this->belongsTo(ClinicDepartment::class);
    }

    public function clinic(): HasOneThrough
    {
        return $this->hasOneThrough(Clinic::class, ClinicDepartment::class);
    }

    public function resourceType(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function partnerProducts(): BelongsToMany
    {
        return $this->belongsToMany(PartnerProduct::class, 'partner_product_resource');
    }

    /**
     * Check if the resource has infinite duration (no end date on any shift).
     */
    public function hasInfiniteDuration(): bool
    {
        return $this->shifts->contains(function ($shift) {
            return $shift->period()->isInfinite();
        });
    }
}
