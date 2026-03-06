<?php

namespace App\Models;

use App\Enums\ProductReports;
use App\Enums\ResourceType as ResourceTypeEnum;
use App\Models\Abstracts\BaseProduct;
use Exception;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Webkul\Product\Models\Product;

/**
 * Note; sales prices only used as back-up. Use Product sales price
 *
 * @mixin IdeHelperPartnerProduct
 */
class PartnerProduct extends BaseProduct
{
    use SoftDeletes;

    protected $table = 'partner_products';

    protected $fillable = [
        // base fields
        'external_id',
        'currency',
        'sales_price',
        'related_sales_price',
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
        'reporting',
        'deleted_at',
    ];

    protected $casts = [
        'sales_price'                   => 'decimal:2',
        'related_sales_price'           => 'decimal:2',
        'active'                        => 'boolean',
        'resource_type_id'              => 'integer',
        'product_id'                    => 'integer',
        'created_by'                    => 'integer',
        'updated_by'                    => 'integer',
        'duration'  => 'integer',
        'reporting' => 'array',
        'deleted_at'                    => 'datetime',
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

    /**
     * Get the reporting attribute, ensuring it's always an array.
     */
    public function getReportingAttribute($value)
    {
        if (is_null($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        // If it's a string, try to decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    public function purchasePrice(): MorphOne
    {
        return $this->morphOne(PurchasePrice::class, 'priceable')->where('type', 'main');
    }

    public function relatedPurchasePrice(): MorphOne
    {
        return $this->morphOne(PurchasePrice::class, 'priceable')->where('type', 'related');
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
        )->whereNull('partner_products.deleted_at');
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

    /**
     * @return bool if the product is plannable (i.e. not of type OTHER and has at least one active clinic)
     */
    public function isPlannable(): bool
    {
        // ignore, too much relations and these clinics won't have a schedule; $this->clinics()->where('is_active', true)->exists()
        try {
            return ResourceTypeEnum::mapFrom($this->resourceType?->name) !== ResourceTypeEnum::OTHER;
        } catch (Exception $e) {
            // all custom created resource types will be handled as not plannable.
            return false;
        }

    }
}
