<?php

namespace App\Models;

use App\Support\AddressSupport;
use App\Support\PostcodeNormalizer;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperAddress
 */
class Address extends BaseModel
{
    use HasAuditTrail, HasFactory;

    /**
     * The validation rules.
     *
     * @var array
     */
    public static $rules = [
        'street'              => 'nullable|string|max:255',
        'house_number'        => 'required|string|max:50',
        'postal_code'         => 'nullable|string|max:20',
        'house_number_suffix' => 'nullable|string|max:10',
        'state'               => 'nullable|string|max:255',
        'city'                => 'nullable|string|max:255',
        'country'             => 'nullable|string|max:255',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'street',
        'house_number',
        'postal_code',
        'house_number_suffix',
        'state',
        'city',
        'country',
        'created_by',
        'updated_by',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($address) {
            // Normalize and trim incoming address fields
            foreach (['street', 'house_number', 'house_number_suffix', 'state', 'city', 'country'] as $field) {
                if (isset($address->{$field}) && is_string($address->{$field})) {
                    $address->{$field} = trim($address->{$field});
                }
            }

            // Normalize postal code globally (trim, uppercase, remove internal spaces)
            if (isset($address->postal_code)) {
                $normalized = PostcodeNormalizer::normalize($address->postal_code);
                $address->postal_code = $normalized === '' ? null : $normalized;
            }
        });
    }

    /**
     * Get the full address as a formatted string.
     */
    public function getFullAddressAttribute()
    {
        $parts = [];

        if ($this->street && $this->house_number) {
            $houseNumber = $this->house_number;
            if ($this->house_number_suffix) {
                $houseNumber .= ' '.$this->house_number_suffix;
            }
            $parts[] = $this->street.' '.$houseNumber;
        }

        $line2 = AddressSupport::formatLine2($this);
        if ($line2 !== '') {
            if ($this->state) {
                $line2 .= ', '.$this->state;
            }
            $parts[] = $line2;
        }

        if ($this->country) {
            $parts[] = $this->country;
        }

        return implode(', ', $parts);
    }

    /**
     * Format address as string for email templates.
     * Returns street + house number and postal code + city.
     */
    public function formatAddress(): string
    {
        return AddressSupport::formatFull($this, includeCountry: false);
    }

    public function getMultilineAddressAttribute(): string
    {
        return AddressSupport::formatMultiline($this);
    }
}
