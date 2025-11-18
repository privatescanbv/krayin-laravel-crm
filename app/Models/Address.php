<?php

namespace App\Models;

use App\Support\PostcodeNormalizer;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use InvalidArgumentException;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

class Address extends BaseModel
{
    use HasAuditTrail, HasFactory;

    /**
     * The validation rules.
     *
     * @var array
     */
    public static $rules = [
        'lead_id'             => 'required_without_all:person_id,organization_id|nullable|exists:leads,id',
        'person_id'           => 'required_without_all:lead_id,organization_id|nullable|exists:persons,id',
        'organization_id'     => 'required_without_all:lead_id,person_id|nullable|exists:organizations,id',
        'street'              => 'nullable|string|max:255',
        'house_number'        => 'required|string|max:50',
        'postal_code'         => 'required|string|max:20',
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
        'lead_id',
        'person_id',
        'organization_id',
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
            // Ensure at least one of lead_id, person_id, or organization_id is set
            if (empty($address->lead_id) && empty($address->person_id) && empty($address->organization_id)) {
                throw new InvalidArgumentException('Either lead_id, person_id, or organization_id must be provided');
            }

            // Ensure only one is set
            $setFields = array_filter([
                $address->lead_id,
                $address->person_id,
                $address->organization_id,
            ]);

            if (count($setFields) > 1) {
                throw new InvalidArgumentException('Cannot set multiple entity IDs (lead_id, person_id, organization_id)');
            }

            // Normalize and trim incoming address fields
            foreach (['street', 'house_number', 'house_number_suffix', 'state', 'city', 'country'] as $field) {
                if (isset($address->{$field}) && is_string($address->{$field})) {
                    $address->{$field} = trim($address->{$field});
                }
            }

            // Normalize postal code globally (trim, uppercase, remove internal spaces)
            if (isset($address->postal_code)) {
                $address->postal_code = PostcodeNormalizer::normalize($address->postal_code);
            }
        });
    }

    /**
     * Get the lead that owns the address.
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the person that owns the address.
     */
    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Get the organization that owns the address.
     */
    public function organization()
    {
        return $this->belongsTo(\Webkul\Contact\Models\Organization::class);
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

        if ($this->postal_code && $this->city) {
            $displayPostal = $this->formatPostalCodeForDisplay($this->postal_code);
            $cityPart = $displayPostal.' '.$this->city;
            if ($this->state) {
                $cityPart .= ', '.$this->state;
            }
            $parts[] = $cityPart;
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
        $addressParts = [];

        if ($this->street && $this->house_number) {
            $houseNumber = $this->house_number;
            if ($this->house_number_suffix) {
                $houseNumber .= ' '.$this->house_number_suffix;
            }
            $addressParts[] = $this->street.' '.$houseNumber;
        }

        if ($this->postal_code && $this->city) {
            $postalCode = $this->formatPostalCodeForDisplay($this->postal_code);
            $addressParts[] = $postalCode.' '.$this->city;
        }

        return implode(', ', $addressParts);
    }

    /**
     * Format stored postal codes for display. Keeps global support; only adds a space
     * for Dutch-style codes like 1234AB -> 1234 AB. Otherwise leaves as-is.
     */
    private function formatPostalCodeForDisplay(string $postalCode): string
    {
        if (preg_match('/^([0-9]{4})([A-Z]{2})$/u', $postalCode, $m)) {
            return $m[1].' '.$m[2];
        }

        return $postalCode;
    }
}
