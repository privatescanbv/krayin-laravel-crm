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
        'created_by',
        'updated_by',
        // partner specific
        'clinic_description',
        'duration',
        // purchase price fields
        'purchase_price',
        'purchase_price_misc',
        'purchase_price_doctor',
        'purchase_price_cardiology',
        'purchase_price_clinic',
        'purchase_price_royal_doctors',
        'purchase_price_radiology',
    ];

    protected $casts = [
        'sales_price'                   => 'decimal:2',
        'active'                        => 'boolean',
        'resource_type_id'              => 'integer',
        'created_by'                    => 'integer',
        'updated_by'                    => 'integer',
        'duration'                      => 'integer',
        'purchase_price'                => 'decimal:2',
        'purchase_price_misc'           => 'decimal:2',
        'purchase_price_doctor'         => 'decimal:2',
        'purchase_price_cardiology'     => 'decimal:2',
        'purchase_price_clinic'         => 'decimal:2',
        'purchase_price_royal_doctors'  => 'decimal:2',
        'purchase_price_radiology'      => 'decimal:2',
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

    public function resources()
    {
        return $this->belongsToMany(Resource::class, 'partner_product_resource');
    }

    public function products()
    {
        return $this->belongsToMany(\Webkul\Product\Models\Product::class, 'product_partner_product');
    }
}
