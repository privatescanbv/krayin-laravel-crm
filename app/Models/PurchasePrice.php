<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PurchasePrice extends Model
{
    protected $fillable = [
        'priceable_type',
        'priceable_id',
        'type',
        'purchase_price_misc',
        'purchase_price_doctor',
        'purchase_price_cardiology',
        'purchase_price_clinic',
        'purchase_price_radiology',
        'purchase_price',
    ];

    protected $casts = [
        'purchase_price_misc'       => 'decimal:2',
        'purchase_price_doctor'     => 'decimal:2',
        'purchase_price_cardiology' => 'decimal:2',
        'purchase_price_clinic'     => 'decimal:2',
        'purchase_price_radiology'  => 'decimal:2',
        'purchase_price'            => 'decimal:2',
    ];

    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }
}
