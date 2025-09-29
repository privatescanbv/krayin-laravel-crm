<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clinic extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'clinics';

    protected $fillable = [
        'external_id',
        'name',
        'soort',
        'emails',
        'phones',
        'address_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
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
}
