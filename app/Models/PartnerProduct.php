<?php

namespace App\Models;

use App\Enums\ProductReports;
use App\Models\Abstracts\BaseProduct;
use Illuminate\Support\Collection;
use Webkul\Product\Models\Product;

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
        'product_id',
        'created_by',
        'updated_by',
        // partner specific
        'clinic_description',
        'duration',
        'purchase_price_misc',
        'purchase_price_doctor',
        'purchase_price_cardiology',
        'purchase_price_clinic',
        'purchase_price_royal_doctors',
        'purchase_price_radiology',
        'purchase_price',
        'reporting',
    ];

    protected $casts = [
        'sales_price'                  => 'decimal:2',
        'active'                       => 'boolean',
        'resource_type_id'             => 'integer',
        'product_id'                   => 'integer',
        'created_by'                   => 'integer',
        'updated_by'                   => 'integer',
        'duration'                     => 'integer',
        'purchase_price_misc'          => 'decimal:2',
        'purchase_price_doctor'        => 'decimal:2',
        'purchase_price_cardiology'    => 'decimal:2',
        'purchase_price_clinic'        => 'decimal:2',
        'purchase_price_royal_doctors' => 'decimal:2',
        'purchase_price_radiology'     => 'decimal:2',
        'purchase_price'               => 'decimal:2',
        'reporting'                    => 'array',
    ];

    /**
     * Normalize various reporting input shapes (array, JSON string, comma-separated string, collection) to array.
     */
    public static function normalizeReporting(mixed $input): array
    {
        if ($input instanceof Collection) {
            return $input->all();
        }

        if (is_array($input)) {
            return $input;
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) {
                    return $decoded;
                }
                if (is_string($decoded) && $decoded !== '') {
                    return [$decoded];
                }

                return [];
            }

            $parts = array_filter(array_map('trim', explode(',', $input)));

            return $parts;
        }

        return [];
    }

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

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getReportingOptions(): array
    {
        return ProductReports::getOptions();
    }

    public function getReportingLabels(): array
    {
        if (! $this->reporting) {
            return [];
        }

        $labels = [];
        foreach ($this->reporting as $report) {
            $enum = ProductReports::tryFrom($report);
            if ($enum) {
                $labels[] = $enum->getLabel();
            }
        }

        return $labels;
    }
}
