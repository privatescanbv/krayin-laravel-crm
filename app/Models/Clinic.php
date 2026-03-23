<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use App\Traits\HasDefaultContactInfo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'order_confirmation_note',
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

    public function visitAddress()
    {
        return $this->belongsTo(Address::class, 'visit_address_id');
    }

    public function postalAddress()
    {
        return $this->belongsTo(Address::class, 'postal_address_id');
    }

    /**
     * Backwards-compatible alias for the old single address relation.
     * Treats the visit address as the primary address.
     */
    public function address()
    {
        return $this->visitAddress();
    }

    public function partnerProducts()
    {
        return $this->belongsToMany(PartnerProduct::class, 'clinic_partner_product');
    }

    public function resources()
    {
        return $this->hasMany(Resource::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function emails()
    {
        return $this->hasMany(Email::class);
    }

    public function afbDispatches(): HasMany
    {
        return $this->hasMany(AfbDispatch::class);
    }

    public function afbDispatchOrders(): HasMany
    {
        return $this->hasMany(AfbDispatchOrder::class);
    }

    public function label(): string
    {
        return $this->name.' | '.$this->visitAddress?->formatAddress();
    }
}
