<?php

namespace App\Models;

use App\Enums\PurchasePriceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

/**
 * @property PurchasePriceType $type
 *
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
        'force_received',
    ];

    protected $casts = [
        'type'                      => PurchasePriceType::class,
        'force_received'            => 'boolean',
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

        return collect((new self)->getFillable())
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

    protected static function booted(): void
    {
        static::saving(static function (PurchasePrice $price): void {
            $total = $price->purchase_price;
            if ($total === null) {
                return;
            }

            // Only validate when all component fields are explicitly set (not null).
            // Allows partial saves (e.g. setting only the total) without triggering the check.
            foreach (self::priceSuffixes() as $suffix) {
                if ($price->{'purchase_price_'.$suffix} === null) {
                    return;
                }
            }

            $componentSum = round(
                array_sum(array_map(
                    fn (string $suffix) => (float) ($price->{'purchase_price_'.$suffix} ?? 0),
                    self::priceSuffixes(),
                )),
                2
            );

            if (abs($componentSum - round((float) $total, 2)) >= 0.01) {
                throw new InvalidArgumentException(sprintf(
                    'PurchasePrice totaal/componenten komen niet overeen: componenten optelling=%.2f maar purchase_price=%.2f (type=%s)',
                    $componentSum,
                    (float) $total,
                    $price->type?->value ?? '?',
                ));
            }
        });
    }

    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }
}
