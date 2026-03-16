<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\ProductType as ProductTypeEnum;
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
        'product_type_id',
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
        'order_id'        => 'integer',
        'product_id'      => 'integer',
        'product_type_id' => 'integer',
        'person_id'       => 'integer',
        'quantity'        => 'integer',
        'total_price'     => 'decimal:2',
        'currency'        => 'string',
        'status'          => OrderItemStatus::class,
        'created_by'      => 'integer',
        'updated_by'      => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ProductType::class);
    }

    /**
     * Returns the effective product type ID for this order item.
     *
     * If an override (`order_items.product_type_id`) is set, that wins.
     * Otherwise it falls back to the selected product's `product_type_id`.
     */
    public function resolvedProductTypeId(): ?int
    {
        if (! empty($this->product_type_id)) {
            return (int) $this->product_type_id;
        }

        return $this->product?->product_type_id ? (int) $this->product?->product_type_id : null;
    }

    /**
     * Returns the effective ProductType model for this order item.
     */
    public function resolvedProductType(): ?\App\Models\ProductType
    {
        if (! empty($this->product_type_id)) {
            return $this->productType;
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
        $productType = $this->resolvedProductTypeEnum();

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

            // Not (directly) plannable or not tied to a scan resource type
            ProductTypeEnum::ENDOSCOPIE,
            ProductTypeEnum::LABORATORIUM,
            ProductTypeEnum::VERTALING,
            ProductTypeEnum::DIENSTEN,
            ProductTypeEnum::OVERIG => ResourceTypeEnum::OTHER,
        };
    }

    /**
     * Effective resource type name for planning/monitor.
     *
     * Uses overridden product type (if set) to infer the required resource type.
     * Falls back to the selected product's resource type name.
     */
    public function resolvedResourceTypeName(): ?string
    {
        $fromProductType = $this->resolvedResourceTypeEnum();

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
        return $this->morphOne(PurchasePrice::class, 'priceable')->where('type', 'main');
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
        return $this->morphOne(PurchasePrice::class, 'priceable')->where('type', 'invoice');
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
}
