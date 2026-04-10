<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\ProductType as ProductTypeEnum;
use App\Enums\PurchasePriceType;
use App\Enums\ResourceType as ResourceTypeEnum;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;

/**
 * @mixin IdeHelperOrderItem
 */
class OrderItem extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'order_items';

    protected $appends = ['can_plan'];

    protected $fillable = [
        'order_id',
        'product_id',
        'resource_type_id',
        'name',
        'description',
        'person_id',
        'quantity',
        'total_price',
        'currency',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_id'         => 'integer',
        'product_id'       => 'integer',
        'resource_type_id' => 'integer',
        'person_id'        => 'integer',
        'quantity'         => 'integer',
        'total_price'      => 'decimal:2',
        'currency'         => 'string',
        'status'           => OrderItemStatus::class,
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Optional override of the product's resource type for planning (same FK name as on products).
     */
    public function resourceType(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class);
    }

    /**
     * Effective product type ID for this order item (planning/UI): order-item resource override wins, else product.
     */
    public function resolvedProductTypeId(): ?int
    {
        $type = $this->resolvedProductType();

        return $type ? (int) $type->id : null;
    }

    /**
     * Effective product type for planning/UI: derived from order-item resource type override when set,
     * otherwise the product's product type.
     */
    public function resolvedProductType(): ?ProductType
    {
        if (! empty($this->resource_type_id)) {
            $resource = $this->relationLoaded('resourceType')
                ? $this->resourceType
                : $this->resourceType()->first();

            $productTypeEnum = $this->productTypeEnumFromResourceTypeName($resource?->name);
            if ($productTypeEnum) {
                $row = ProductType::query()->where('name', $productTypeEnum->label())->first();
                if ($row) {
                    return $row;
                }
            }
        }

        return $this->product?->productType;
    }

    public function resolvedProductTypeEnum(): ?ProductTypeEnum
    {
        $name = $this->resolvedProductType()?->name;

        if (! $name) {
            return null;
        }

        foreach (ProductTypeEnum::cases() as $case) {
            if (strcasecmp($case->label(), $name) === 0) {
                return $case;
            }
        }

        return null;
    }

    public function resolvedResourceTypeEnum(): ?ResourceTypeEnum
    {
        if (! empty($this->resource_type_id)) {
            $override = $this->relationLoaded('resourceType')
                ? $this->resourceType
                : $this->resourceType()->first();

            if ($override?->name) {
                try {
                    return ResourceTypeEnum::mapFrom($override->name);
                } catch (\Exception) {
                    // Unknown label: fall through to product type mapping
                }
            }
        }

        return $this->resourceTypeEnumFromProductProductType();
    }

    /**
     * Effective resource type name for planning/monitor.
     *
     * Uses order-item resource type override when set; otherwise product type → resource mapping,
     * then the product's resource type name.
     */
    public function resolvedResourceTypeName(): ?string
    {
        if (! empty($this->resource_type_id)) {
            $name = $this->relationLoaded('resourceType')
                ? $this->resourceType?->name
                : $this->resourceType()->value('name');

            if ($name) {
                return $name;
            }
        }

        $fromProductType = $this->resourceTypeEnumFromProductProductType();

        if ($fromProductType) {
            return $fromProductType->label();
        }

        return $this->product?->resourceType?->name;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function purchasePrice(): MorphOne
    {
        return $this->morphOne(PurchasePrice::class, 'priceable')
            ->where('type', PurchasePriceType::MAIN);
    }

    /**
     * Returns the effective purchase price for this order item.
     * Resolution order per field: 1. order item, 2. product, 3. partner product.
     * Null means "don't override" (use next level). All null = 0.
     */
    public function resolvedPurchasePrice(): object
    {
        $this->loadMissing(['product.partnerProducts.purchasePrice']);

        $suffixes = PurchasePrice::priceSuffixes();
        $orderItemPrice = $this->purchasePrice;
        $product = $this->product;
        $productPrice = $product && method_exists($product, 'purchasePrice') ? $product->purchasePrice : null;

        $partnerProductTotals = null;
        if ($product && ! $product->partnerProducts->isEmpty()) {
            $partnerProductTotals = array_fill_keys($suffixes, 0.0);
            foreach ($product->partnerProducts as $pp) {
                if (! $pp->purchasePrice) {
                    continue;
                }
                foreach ($suffixes as $suffix) {
                    $partnerProductTotals[$suffix] += (float) ($pp->purchasePrice->{'purchase_price_'.$suffix} ?? 0);
                }
            }
        }

        $data = [];
        $total = 0.0;
        foreach ($suffixes as $suffix) {
            $field = 'purchase_price_'.$suffix;
            $orderItemValue = $orderItemPrice?->{$field} ?? null;
            $productValue = $productPrice?->{$field} ?? null;
            $partnerProductValue = $partnerProductTotals[$suffix] ?? null;

            $value = $orderItemValue ?? $productValue ?? $partnerProductValue ?? 0;
            $value = (float) $value;
            $data[$field] = (string) round($value, 2);
            $total += $value;
        }
        $data['purchase_price'] = (string) round($total, 2);

        return (object) $data;
    }

    public function invoicePurchasePrice(): MorphOne
    {
        return $this->morphOne(PurchasePrice::class, 'priceable')
            ->where('type', PurchasePriceType::INVOICE);
    }

    public function resourceOrderItems(): HasMany
    {
        return $this->hasMany(ResourceOrderItem::class, 'orderitem_id');
    }

    public function scopeWithPartnerProductCount(Builder $query): Builder
    {
        return $query->select('order_items.id', 'order_items.status', 'order_items.product_id')
            ->selectRaw('COUNT(partner_products.id) AS partner_product_count')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('partner_products', 'products.id', '=', 'partner_products.product_id')
            ->groupBy('order_items.id', 'order_items.status', 'order_items.product_id');
    }

    public function scopeForOrderAndNotLostAndNew(Builder $query, string $orderId): Builder
    {
        return $query->where('order_id', $orderId)
            ->whereNotIn('status', [OrderItemStatus::LOST->value, OrderItemStatus::NEW->value]);
    }

    public function isPlannable(): bool
    {
        return $this->product &&
            $this->product->partnerProducts &&
            $this->product->partnerProducts->filter(fn ($product) => $product->isPlannable())->count() > 0;
    }

    public function getProductName(): string
    {
        if (! empty($this->name)) {
            return $this->name;
        }

        return $this->product?->name ?? '';
    }

    public function getProductDescription(): string
    {
        if (! empty($this->description)) {
            return $this->description;
        }

        return $this->product?->ndescriptioname ?? '';
    }

    public function getCanPlanAttribute(): string
    {
        return $this->isPlannable();
    }

    /**
     * Product type enum from the linked product only (not order-item override), for resource type fallback chain.
     */
    private function productTypeEnumFromProductOnly(): ?ProductTypeEnum
    {
        $name = $this->product?->productType?->name;

        if (! $name) {
            return null;
        }

        foreach (ProductTypeEnum::cases() as $case) {
            if (strcasecmp($case->label(), $name) === 0) {
                return $case;
            }
        }

        return null;
    }

    private function resourceTypeEnumFromProductProductType(): ?ResourceTypeEnum
    {
        $productType = $this->productTypeEnumFromProductOnly();

        if (! $productType) {
            return null;
        }

        return match ($productType) {
            ProductTypeEnum::TOTAL_BODYSCAN => ResourceTypeEnum::MRI_SCANNER,
            ProductTypeEnum::MRI_SCAN       => ResourceTypeEnum::MRI_SCANNER,
            ProductTypeEnum::CT_SCAN        => ResourceTypeEnum::CT_SCANNER,
            ProductTypeEnum::PETSCAN        => ResourceTypeEnum::PET_CT_SCANNER,
            ProductTypeEnum::CARDIOLOGIE    => ResourceTypeEnum::CARDIOLOGIE,
            ProductTypeEnum::OPERATIONS     => ResourceTypeEnum::ARTSEN,

            ProductTypeEnum::ENDOSCOPIE,
            ProductTypeEnum::LABORATORIUM,
            ProductTypeEnum::VERTALING,
            ProductTypeEnum::DIENSTEN,
            ProductTypeEnum::OVERIG => ResourceTypeEnum::OTHER,
        };
    }

    /**
     * Map planning resource type label to a product type enum for display (reverse of product-type → resource mapping).
     * Ambiguous cases pick a single canonical product type.
     */
    private function productTypeEnumFromResourceTypeName(?string $resourceTypeName): ?ProductTypeEnum
    {
        if ($resourceTypeName === null || $resourceTypeName === '') {
            return null;
        }

        try {
            $resourceEnum = ResourceTypeEnum::mapFrom($resourceTypeName);
        } catch (\Exception) {
            return null;
        }

        return match ($resourceEnum) {
            ResourceTypeEnum::MRI_SCANNER    => ProductTypeEnum::MRI_SCAN,
            ResourceTypeEnum::CT_SCANNER     => ProductTypeEnum::CT_SCAN,
            ResourceTypeEnum::PET_CT_SCANNER => ProductTypeEnum::PETSCAN,
            ResourceTypeEnum::CARDIOLOGIE    => ProductTypeEnum::CARDIOLOGIE,
            ResourceTypeEnum::ARTSEN         => ProductTypeEnum::OPERATIONS,
            ResourceTypeEnum::OTHER          => ProductTypeEnum::OVERIG,
            ResourceTypeEnum::RONTGEN        => ProductTypeEnum::OVERIG,
        };
    }
}
