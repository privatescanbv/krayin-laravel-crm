<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use App\Traits\HasDefaultContactInfo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Webkul\Activity\Models\Activity;
use Webkul\Email\Models\Email;

/**
 * @mixin IdeHelperClinic
 */
class Clinic extends Model
{
    use HasAuditTrail, HasDefaultContactInfo, HasFactory;

    protected $table = 'clinics';

    protected $fillable = [
        'external_id',
        'is_active',
        'name',
        'description',
        'registration_form_clinic_name',
        'website_url',
        'emails',
        'phones',
        'visit_address_id',
        'postal_address_id',
        'is_postal_address_same_as_visit_address',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active'                               => 'boolean',
        'is_postal_address_same_as_visit_address' => 'boolean',
        'emails'                                  => 'array',
        'phones'                                  => 'array',
        'created_by'                              => 'integer',
        'updated_by'                              => 'integer',
    ];

    protected $with = ['visitAddress'];

    public function visitAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'visit_address_id');
    }

    public function postalAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'postal_address_id');
    }

    /**
     * Backwards-compatible alias for the old single address relation.
     * Treats the visit address as the primary address.
     */
    public function address(): BelongsTo
    {
        return $this->visitAddress();
    }

    public function partnerProducts(): BelongsToMany
    {
        return $this->belongsToMany(PartnerProduct::class, 'clinic_partner_product');
    }

    /**
     * Resources belong to clinic departments; this is the effective clinic scope for planning and seeding.
     */
    public function resources(): HasManyThrough
    {
        return $this->hasManyThrough(Resource::class, ClinicDepartment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function afbDispatches(): HasMany
    {
        return $this->hasMany(AfbDispatch::class);
    }

    public function afbPersonDocuments(): HasManyThrough
    {
        return $this->hasManyThrough(AfbPersonDocument::class, AfbDispatch::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(ClinicDepartment::class);
    }

    public function label(): string
    {
        return $this->name.' | '.$this->visitAddress?->formatAddress();
    }
}
