<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'resources';

    protected $fillable = [
        'external_id',
        'name',
        'resource_type_id',
        'clinic_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'clinic_id'        => 'integer',
        'resource_type_id' => 'integer',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function resourceType()
    {
        return $this->belongsTo(ResourceType::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    public function partnerProducts()
    {
        return $this->belongsToMany(PartnerProduct::class, 'partner_product_resource')
            ->whereNull('partner_products.deleted_at');
    }
}
