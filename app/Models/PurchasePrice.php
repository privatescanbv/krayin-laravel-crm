<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperPurchasePrice
 */
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

    /**
     * Returns the suffixes for priceable fields (e.g. misc, doctor, cardiology).
     * Derived from fillable: fields matching purchase_price_* (excluding purchase_price total).
     */
    public static function priceSuffixes(): array
    {
        $prefix = 'purchase_price_';

        return collect((new static)->getFillable())
            ->filter(fn (string $field) => str_starts_with($field, $prefix))
            ->map(fn (string $field) => substr($field, strlen($prefix)))
            ->values()
            ->all();
    }

    /**
     * Returns full field names for priceable columns (e.g. purchase_price_misc, purchase_price_doctor).
     */
    public static function priceableFieldNames(): array
    {
        return array_map(fn (string $suffix) => 'purchase_price_'.$suffix, self::priceSuffixes());
    }

    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }
}
