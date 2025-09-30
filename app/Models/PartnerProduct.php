<?php

namespace App\Models;

use App\Models\Abstracts\BaseProduct;

class PartnerProduct extends BaseProduct
{
    protected $table = 'partner_products';

    protected $fillable = [
        // base fields
        'external_id',
        'currency',
        'sales_price',
        'name',
        'active',
        'description',
        'discount_info',
        'resource_type_id',
        'resource_id',
        'created_by',
        'updated_by',
        // partner specific
        'partner_name',
        'clinic_description',
        'duration',
    ];

    protected $casts = [
        'sales_price'      => 'decimal:2',
        'active'           => 'boolean',
        'resource_type_id' => 'integer',
        'resource_id'      => 'integer',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
        'duration'         => 'integer',
    ];

    public function clinics()
    {
        return $this->belongsToMany(Clinic::class, 'clinic_partner_product');
    }

    public function relatedProducts()
    {
        return $this->belongsToMany(
            PartnerProduct::class,
            'partner_product_related',
            'partner_product_id',
            'related_product_id'
        );
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }
}
