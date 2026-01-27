<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Activity\Models\Activity;
use Webkul\Email\Models\Email;

class Clinic extends Model
{
    use HasAuditTrail, HasFactory;

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
        'address_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'emails'     => 'array',
        'phones'     => 'array',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function address()
    {
        return $this->belongsTo(Address::class);
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

    public function label(): string
    {
        return $this->name.' |'.$this->address?->formatAddress();
    }
}
