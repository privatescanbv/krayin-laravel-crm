<?php

namespace App\Models;

use App\Models\Abstracts\BaseProduct;

class PartnerProduct extends BaseProduct
{
    protected $table = 'partner_products';

    protected $fillable = [
        ...parent::fillable,
        'partner_name',
        'clinic_description',
        'duration',
    ];

    protected $casts = [
        ...parent::casts,
        'duration' => 'integer',
    ];
}

